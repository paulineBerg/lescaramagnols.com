<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PbGestion\Installer;

final class PbGestionAgentInstaller
{
    private const AGENT_RESOURCE = __DIR__ . '/../Resources/agent/pbgestion_agent.py';

    /**
     * @param array<string, mixed> $enrollment
     */
    public function buildPowerShellScript(array $enrollment, string $serverBaseUrl, string $displayName): string
    {
        $agentSource = file_get_contents(self::AGENT_RESOURCE);
        if (!is_string($agentSource) || $agentSource === '') {
            throw new \RuntimeException('PbGestion agent resource not found.');
        }

        $code = is_string($enrollment['code'] ?? null) ? (string) $enrollment['code'] : '';
        if ($code === '') {
            throw new \RuntimeException('Missing enrollment code.');
        }

        $config = [
            'server_base_url' => rtrim($serverBaseUrl, '/'),
            'enrollment_code' => $code,
            'display_name' => $this->portableDisplayName($displayName),
            'data_root' => '$env:LOCALAPPDATA\\PbGestionAgent',
            'allowed_roots' => [
                [
                    'uid' => 'photos-principales',
                    'label' => 'Images Windows',
                    'path' => '$env:USERPROFILE\\Pictures',
                ],
            ],
        ];

        $agentBase64 = base64_encode($agentSource);
        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($configJson)) {
            throw new \RuntimeException('Unable to encode PbGestion agent config.');
        }
        $configBase64 = base64_encode($configJson);
        $expires = is_string($enrollment['expires_at'] ?? null) ? (string) $enrollment['expires_at'] : '';

        return $this->script($agentBase64, $configBase64, $expires);
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
{$expiresComment}\$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host 'INSTALLATION LOCALE PB GESTION'
Write-Host 'Cette action installe un agent local pour l utilisateur Windows courant.'
Write-Host 'L agent cree des fichiers dans %LOCALAPPDATA%\\PbGestionAgent, cree une tache planifiee locale,'
Write-Host 's appaire au BO Private, puis execute uniquement les commandes signees et bornees par vos racines locales autorisees.'
Write-Host 'Sans votre validation dans le BO et dans ce script, aucune installation silencieuse n est effectuee.'
Write-Host ''
\$confirmation = Read-Host 'Tapez OUI pour confirmer l installation locale'
if (\$confirmation -ne 'OUI') {
    Write-Host 'Installation refusee. Le BO reste utilisable en mode restreint sans agent.'
    exit 12
}

\$installRoot = Join-Path \$env:LOCALAPPDATA 'PbGestionAgent'
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
}
