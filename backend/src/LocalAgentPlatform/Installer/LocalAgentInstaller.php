<?php

declare(strict_types=1);

namespace Caramagnols\LocalAgentPlatform\Installer;

final class LocalAgentInstaller
{
    private const AGENT_RESOURCE = __DIR__ . '/../Resources/agent/pbgestion_agent.py';

    /**
     * @param array<string, mixed> $enrollment
     */
    public function buildPowerShellScript(array $enrollment, string $serverBaseUrl, string $displayName): string
    {
        $agentSource = $this->agentSource();
        $config = $this->config($enrollment, $serverBaseUrl, $displayName, [
            'server_base_url' => rtrim($serverBaseUrl, '/'),
            'data_root' => '$env:LOCALAPPDATA\\pbgestion\\agent',
            'allowed_roots' => [
                [
                    'uid' => 'photos-principales',
                    'label' => 'Images Windows',
                    'path' => '$env:USERPROFILE\\Pictures',
                ],
            ],
        ]);

        $agentBase64 = base64_encode($agentSource);
        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($configJson)) {
            throw new \RuntimeException('Unable to encode PbGestion agent config.');
        }
        $configBase64 = base64_encode($configJson);
        $expires = is_string($enrollment['expires_at'] ?? null) ? (string) $enrollment['expires_at'] : '';

        return $this->script($agentBase64, $configBase64, $expires);
    }

    /**
     * @param array<string, mixed> $enrollment
     */
    public function buildUnixShellScript(array $enrollment, string $serverBaseUrl, string $displayName, string $platform): string
    {
        $agentSource = $this->agentSource();
        $platform = strtolower(trim($platform)) === 'macos' ? 'macos' : 'linux';
        $dataRoot = $platform === 'macos'
            ? '$HOME/Library/Application Support/pbgestion/agent'
            : '$HOME/.local/share/pbgestion/agent';
        $pictureLabel = $platform === 'macos' ? 'Images macOS' : 'Images Linux';
        $config = $this->config($enrollment, $serverBaseUrl, $displayName, [
            'server_base_url' => rtrim($serverBaseUrl, '/'),
            'data_root' => $dataRoot,
            'allowed_roots' => [
                [
                    'uid' => 'photos-principales',
                    'label' => $pictureLabel,
                    'path' => '$HOME/Pictures',
                ],
            ],
        ]);

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($configJson)) {
            throw new \RuntimeException('Unable to encode PbGestion agent config.');
        }

        return $this->unixScript(
            base64_encode($agentSource),
            base64_encode($configJson),
            is_string($enrollment['expires_at'] ?? null) ? (string) $enrollment['expires_at'] : '',
            $platform,
            $dataRoot
        );
    }

    public function buildPowerShellUninstallScript(): string
    {
        return <<<'POWERSHELL'
# Suppression locale PbGestion pour Windows.
$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host 'SUPPRESSION LOCALE PB GESTION'
Write-Host 'Ce script supprime la tache planifiee locale et le dossier agent du profil Windows courant.'
Write-Host 'Revoquez aussi l agent dans le BO Private pour bloquer ses prochaines synchronisations.'
Write-Host ''

$taskName = 'PbGestionAgent'
$pbGestionRoot = Join-Path $env:LOCALAPPDATA 'pbgestion'
$installRoot = Join-Path $pbGestionRoot 'agent'

$task = schtasks.exe /Query /TN "$taskName" 2>$null
if ($LASTEXITCODE -eq 0) {
    schtasks.exe /Delete /F /TN "$taskName" | Out-Null
    Write-Host 'Tache planifiee supprimee: PbGestionAgent.'
}

if (Test-Path $installRoot) {
    Remove-Item -Recurse -Force $installRoot
    Write-Host "Dossier agent supprime: $installRoot"
} else {
    Write-Host 'Aucun dossier agent local trouve.'
}

Write-Host 'Suppression locale terminee.'
POWERSHELL;
    }

    public function buildUnixUninstallScript(string $platform): string
    {
        $platform = strtolower(trim($platform)) === 'macos' ? 'macos' : 'linux';
        $dataRoot = $platform === 'macos'
            ? '${HOME}/Library/Application Support/pbgestion/agent'
            : '${HOME}/.local/share/pbgestion/agent';

        return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

echo ''
echo 'SUPPRESSION LOCALE PB GESTION'
echo 'Ce script supprime les services locaux et le dossier agent du profil courant.'
echo 'Revoquez aussi l agent dans le BO Private pour bloquer ses prochaines synchronisations.'
echo ''

INSTALL_ROOT="$dataRoot"
if command -v systemctl >/dev/null 2>&1; then
  systemctl --user disable --now pbgestion-agent.timer >/dev/null 2>&1 || true
  rm -f "\${HOME}/.config/systemd/user/pbgestion-agent.service" "\${HOME}/.config/systemd/user/pbgestion-agent.timer"
  systemctl --user daemon-reload >/dev/null 2>&1 || true
fi

if [ "\$(uname -s)" = "Darwin" ]; then
  PLIST="\${HOME}/Library/LaunchAgents/com.lescaramagnols.pbgestion-agent.plist"
  launchctl bootout "gui/\$(id -u)" "\$PLIST" >/dev/null 2>&1 || true
  rm -f "\$PLIST"
fi

if [ -d "\$INSTALL_ROOT" ]; then
  rm -rf "\$INSTALL_ROOT"
  echo "Dossier agent supprime: \$INSTALL_ROOT"
else
  echo 'Aucun dossier agent local trouve.'
fi

echo 'Suppression locale terminee.'
BASH;
    }

    /**
     * @param array<string, mixed> $enrollment
     * @param array<string, mixed> $platformConfig
     * @return array<string, mixed>
     */
    private function config(array $enrollment, string $serverBaseUrl, string $displayName, array $platformConfig): array
    {
        $code = is_string($enrollment['code'] ?? null) ? (string) $enrollment['code'] : '';
        if ($code === '') {
            throw new \RuntimeException('Missing enrollment code.');
        }

        return array_replace($platformConfig, [
            'server_base_url' => rtrim($serverBaseUrl, '/'),
            'enrollment_code' => $code,
            'display_name' => $this->portableDisplayName($displayName),
        ]);
    }

    private function agentSource(): string
    {
        $agentSource = file_get_contents(self::AGENT_RESOURCE);
        if (!is_string($agentSource) || $agentSource === '') {
            throw new \RuntimeException('PbGestion agent resource not found.');
        }

        return $agentSource;
    }

    private function portableDisplayName(string $displayName): string
    {
        $displayName = trim($displayName);
        if ($displayName === '') {
            return 'PbGestion Agent';
        }

        return mb_substr($displayName, 0, 120);
    }

    private function script(string $agentBase64, string $configBase64, string $expiresAt): string
    {
        $expiresComment = $expiresAt !== '' ? '# Code valable jusqu\'a ' . $expiresAt . " UTC.\r\n" : '';

        return <<<POWERSHELL
# Installeur local PbGestion pour Windows.
# Genere depuis le BO Private apres consentement explicite.
# Endpoint d appairage: /api/pbgestion/v1/enrollment/claim
{$expiresComment}# Le code d appairage est inclus dans config.json et supprime apres appairage.
\$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host 'INSTALLATION LOCALE PB GESTION'
Write-Host 'Cette action installe un agent local pour l utilisateur Windows courant.'
Write-Host 'L agent cree des fichiers dans %LOCALAPPDATA%\\pbgestion\\agent, cree une tache planifiee locale,'
Write-Host 's appaire au BO Private, puis execute uniquement les commandes signees et bornees par vos racines locales autorisees.'
Write-Host ''

\$pbGestionRoot = Join-Path \$env:LOCALAPPDATA 'pbgestion'
\$installRoot = Join-Path \$pbGestionRoot 'agent'
\$agentPath = Join-Path \$installRoot 'pbgestion_agent.py'
\$configPath = Join-Path \$installRoot 'config.json'
\$venvPath = Join-Path \$installRoot '.venv'
\$taskName = 'PbGestionAgent'

New-Item -ItemType Directory -Force -Path \$installRoot | Out-Null
[IO.File]::WriteAllBytes(\$agentPath, [Convert]::FromBase64String('$agentBase64'))
\$configText = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('$configBase64'))
\$configText = \$configText.Replace('\$env:LOCALAPPDATA', \$env:LOCALAPPDATA).Replace('\$env:USERPROFILE', \$env:USERPROFILE)
[IO.File]::WriteAllText(\$configPath, \$configText, [Text.UTF8Encoding]::new(\$false))

\$pythonLauncher = \$null
foreach (\$candidate in @('py', 'python')) {
    \$cmd = Get-Command \$candidate -ErrorAction SilentlyContinue
    if (\$cmd -ne \$null) {
        \$pythonLauncher = \$candidate
        break
    }
}
if (\$pythonLauncher -eq \$null) {
    throw 'Python 3 est requis. Installez Python depuis python.org puis relancez cet installeur.'
}

if (-not (Test-Path \$venvPath)) {
    & \$pythonLauncher -m venv \$venvPath
}
\$pythonExe = Join-Path \$venvPath 'Scripts\\python.exe'
if (-not (Test-Path \$pythonExe)) {
    throw 'Environnement Python local introuvable apres creation.'
}

& \$pythonExe -m pip install --upgrade pip
& \$pythonExe -m pip install pynacl
& \$pythonExe \$agentPath enroll --config \$configPath

\$taskCommand = "`"\$pythonExe`" `"\$agentPath`" run-once --config `"\$configPath`""
\$taskArgs = "/Create /F /SC MINUTE /MO 5 /TN `"\$taskName`" /TR `"\$taskCommand`""
\$process = Start-Process -FilePath schtasks.exe -ArgumentList \$taskArgs -NoNewWindow -PassThru -Wait
if (\$process.ExitCode -ne 0) {
    Write-Host 'La tache planifiee n a pas pu etre creee automatiquement.'
    Write-Host "Commande manuelle: \$taskCommand"
} else {
    Write-Host 'Tache planifiee locale creee: PbGestionAgent, toutes les 5 minutes.'
}

& \$pythonExe \$agentPath run-once --config \$configPath
Write-Host 'Installation terminee. Vous pouvez verifier le dernier contact dans le BO Private.'
POWERSHELL;
    }

    private function unixScript(
        string $agentBase64,
        string $configBase64,
        string $expiresAt,
        string $platform,
        string $dataRoot
    ): string {
        $expiresComment = $expiresAt !== '' ? '# Code valable jusqu a ' . $expiresAt . " UTC.\n" : '';
        $serviceBlock = $platform === 'macos' ? <<<'BASH'
PLIST="${HOME}/Library/LaunchAgents/com.lescaramagnols.pbgestion-agent.plist"
mkdir -p "${HOME}/Library/LaunchAgents"
cat > "$PLIST" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>com.lescaramagnols.pbgestion-agent</string>
  <key>ProgramArguments</key><array>
    <string>${PYTHON_EXE}</string><string>${AGENT_PATH}</string><string>run-once</string><string>--config</string><string>${CONFIG_PATH}</string>
  </array>
  <key>StartInterval</key><integer>300</integer>
  <key>RunAtLoad</key><true/>
  <key>StandardOutPath</key><string>${INSTALL_ROOT}/launchd.out.log</string>
  <key>StandardErrorPath</key><string>${INSTALL_ROOT}/launchd.err.log</string>
</dict></plist>
PLIST
launchctl bootstrap "gui/$(id -u)" "$PLIST" >/dev/null 2>&1 || true
launchctl kickstart -k "gui/$(id -u)/com.lescaramagnols.pbgestion-agent" >/dev/null 2>&1 || true
echo "Agent macOS configure via LaunchAgent."
BASH : <<<'BASH'
if command -v systemctl >/dev/null 2>&1; then
  mkdir -p "${HOME}/.config/systemd/user"
  cat > "${HOME}/.config/systemd/user/pbgestion-agent.service" <<SERVICE
[Unit]
Description=PbGestion local agent

[Service]
Type=oneshot
ExecStart=${PYTHON_EXE} ${AGENT_PATH} run-once --config ${CONFIG_PATH}
SERVICE
  cat > "${HOME}/.config/systemd/user/pbgestion-agent.timer" <<TIMER
[Unit]
Description=Run PbGestion local agent every 5 minutes

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
Unit=pbgestion-agent.service

[Install]
WantedBy=timers.target
TIMER
  systemctl --user daemon-reload
  systemctl --user enable --now pbgestion-agent.timer
  echo "Agent Linux configure via systemd utilisateur."
else
  echo "systemd utilisateur indisponible. Commande manuelle:"
  echo "${PYTHON_EXE} ${AGENT_PATH} run-once --config ${CONFIG_PATH}"
fi
BASH;

        return <<<BASH
#!/usr/bin/env bash
set -euo pipefail

# Installeur local PbGestion pour $platform.
# Genere depuis le BO Private apres consentement explicite.
# Endpoint d appairage: /api/pbgestion/v1/enrollment/claim
{$expiresComment}# Le code d appairage est inclus dans config.json et supprime apres appairage.

echo ''
echo 'INSTALLATION LOCALE PB GESTION'
echo 'Cette action installe un agent local pour l utilisateur courant.'
echo 'L agent cree des fichiers dans le profil local, s appaire au BO Private,'
echo 'puis execute uniquement les commandes signees et bornees par vos racines locales autorisees.'
echo ''

INSTALL_ROOT="$dataRoot"
AGENT_PATH="\${INSTALL_ROOT}/pbgestion_agent.py"
CONFIG_PATH="\${INSTALL_ROOT}/config.json"
VENV_PATH="\${INSTALL_ROOT}/.venv"
export INSTALL_ROOT AGENT_PATH CONFIG_PATH

mkdir -p "\$INSTALL_ROOT"
python3 - <<'PY'
import base64
import os
from pathlib import Path

root = Path(os.environ["INSTALL_ROOT"]).expanduser()
root.mkdir(parents=True, exist_ok=True)
(root / "pbgestion_agent.py").write_bytes(base64.b64decode("$agentBase64"))
config = base64.b64decode("$configBase64").decode("utf-8").replace("\$HOME", os.environ.get("HOME", ""))
(root / "config.json").write_text(config, encoding="utf-8")
PY

python3 -m venv "\$VENV_PATH"
PYTHON_EXE="\${VENV_PATH}/bin/python"
"\$PYTHON_EXE" -m pip install --upgrade pip
"\$PYTHON_EXE" -m pip install pynacl
"\$PYTHON_EXE" "\$AGENT_PATH" enroll --config "\$CONFIG_PATH"

$serviceBlock

"\$PYTHON_EXE" "\$AGENT_PATH" run-once --config "\$CONFIG_PATH"
echo 'Installation terminee. Vous pouvez verifier le dernier contact dans le BO Private.'
BASH;
    }
}
