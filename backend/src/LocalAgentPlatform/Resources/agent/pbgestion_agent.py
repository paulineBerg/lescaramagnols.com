#!/usr/bin/env python3
"""PbGestion local agent.

The server keeps originals out of OVH. This agent runs on the local computer,
enrolls with a short-lived BO code, signs requests with Ed25519, polls queued
commands, and executes photo commands only inside configured local roots.
"""

from __future__ import annotations

import argparse
import base64
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import sys
import uuid
from urllib import request, error

try:
    from nacl.signing import SigningKey
except Exception as exc:  # pragma: no cover - installer handles dependency
    print("PyNaCl is required: python -m pip install pynacl", file=sys.stderr)
    raise SystemExit(2) from exc


VERSION = "0.3.0"
PHOTO_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp", ".heic"}


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def read_json(path: Path) -> dict:
    if not path.exists():
        return {}
    return json.loads(path.read_text(encoding="utf-8"))


def write_json(path: Path, value: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False, sort_keys=True), encoding="utf-8")


def post_json(url: str, payload: dict, headers: dict | None = None) -> dict:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    req = request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json", **(headers or {})},
        method="POST",
    )
    try:
        with request.urlopen(req, timeout=30) as response:
            raw = response.read().decode("utf-8")
    except error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"HTTP {exc.code}: {raw}") from exc
    return json.loads(raw)


def canonical_request(method: str, path: str, body: bytes, timestamp: str, sequence: int, request_id: str) -> bytes:
    return "\n".join(
        [
            method.upper(),
            path,
            hashlib.sha256(body).hexdigest(),
            timestamp,
            str(sequence),
            request_id.lower(),
        ]
    ).encode("utf-8")


def signed_post(config: dict, path: str, payload: dict) -> dict:
    body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
    sequence = int(config.get("sequence", 0)) + 1
    timestamp = utc_now()
    request_id = str(uuid.uuid4())
    signing_key = SigningKey(base64.b64decode(str(config["private_key_base64"])))
    signature = signing_key.sign(canonical_request("POST", path, body, timestamp, sequence, request_id)).signature
    headers = {
        "X-PB-Agent-Uid": str(config["agent_uid"]),
        "X-PB-Timestamp": timestamp,
        "X-PB-Sequence": str(sequence),
        "X-PB-Request-Id": request_id,
        "X-PB-Signature": base64.b64encode(signature).decode("ascii"),
    }
    result = post_json(config["server_base_url"].rstrip("/") + path, payload, headers)
    config["sequence"] = sequence
    return result


def enroll(config_path: Path) -> None:
    config = read_json(config_path)
    code = str(config.get("enrollment_code", "")).replace("-", "").replace(" ", "").strip()
    if not code:
        raise RuntimeError("Missing enrollment_code in bootstrap config.")

    signing_key = SigningKey.generate()
    public_key = signing_key.verify_key.encode()
    payload = {
        "code": code,
        "public_key_base64": base64.b64encode(public_key).decode("ascii"),
        "display_name": config.get("display_name") or os.environ.get("COMPUTERNAME") or "PbGestion Agent",
        "os_family": "windows" if os.name == "nt" else sys.platform,
        "os_version": platform_label(),
        "agent_version": VERSION,
        "capabilities": ["photos"],
    }
    result = post_json(config["server_base_url"].rstrip("/") + "/api/pbgestion/v1/enrollment/claim", payload)
    if result.get("ok") is not True:
        raise RuntimeError(f"Enrollment rejected: {result}")

    config["agent_uid"] = result["agent_uid"]
    config["private_key_base64"] = base64.b64encode(signing_key.encode()).decode("ascii")
    config["public_key_base64"] = payload["public_key_base64"]
    config["sequence"] = 0
    config.pop("enrollment_code", None)
    write_json(config_path, config)
    print("PbGestion agent enrolled:", config["agent_uid"])


def platform_label() -> str:
    if os.name == "nt":
        return f"Windows {os.environ.get('OS', '')}".strip()
    return sys.platform


def run_once(config_path: Path) -> None:
    config = read_json(config_path)
    signed_post(
        config,
        "/api/pbgestion/v1/sync",
        {
            "agent_version": VERSION,
            "os_version": platform_label(),
            "capabilities": ["photos"],
        },
    )
    commands = signed_post(config, "/api/pbgestion/v1/commands/poll", {}).get("commands", [])
    write_json(config_path, config)
    for command in commands:
        if not isinstance(command, dict):
            continue
        handle_command(config_path, command)


def handle_command(config_path: Path, command: dict) -> None:
    config = read_json(config_path)
    command_uid = str(command.get("command_uid", ""))
    command_type = str(command.get("command_type", ""))
    payload = command.get("payload") if isinstance(command.get("payload"), dict) else {}
    ack(config, command_uid, "running", "running", "Command started")
    try:
        message = execute_photo_command(config, command_type, payload)
        ack(config, command_uid, "succeeded", "ok", message)
    except Exception as exc:
        ack(config, command_uid, "failed", "local_error", str(exc)[:220])
    write_json(config_path, config)


def ack(config: dict, command_uid: str, status: str, code: str, message: str) -> None:
    signed_post(
        config,
        "/api/pbgestion/v1/commands/ack",
        {
            "command_uid": command_uid,
            "status": status,
            "result_code": code,
            "result_message": message[:240],
        },
    )


def execute_photo_command(config: dict, command_type: str, payload: dict) -> str:
    if command_type == "photo.roots.list":
        roots = config.get("allowed_roots") if isinstance(config.get("allowed_roots"), list) else []
        labels = [str(root.get("uid")) for root in roots if isinstance(root, dict)]
        log_local(config, {"event": "roots.list", "roots": roots})
        return "Racines locales: " + ", ".join(labels)
    if command_type == "photo.folder.scan":
        folder = resolve_photo_folder(config, payload)
        files = sorted([p.name for p in folder.iterdir() if p.is_file() and p.suffix.lower() in PHOTO_EXTENSIONS])
        log_local(config, {"event": "folder.scan", "folder": str(folder), "count": len(files), "files": files[:200]})
        return f"{len(files)} photo(s) trouvee(s). Detail dans le journal local."
    if command_type == "photo.rename.preview":
        preview_uid = uuid.uuid4().hex
        preview = build_rename_preview(config, payload, preview_uid)
        preview_path(config, str(payload.get("batch_uid", "")), preview_uid).write_text(
            json.dumps(preview, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )
        return f"Apercu cree. batch_uid={payload.get('batch_uid')} preview_uid={preview_uid}"
    if command_type == "photo.rename.execute":
        preview = read_preview(config, payload)
        apply_operations(preview.get("operations", []))
        return f"Renommage termine pour {len(preview.get('operations', []))} fichier(s)."
    if command_type == "photo.rename.rollback_preview":
        rollback_uid = uuid.uuid4().hex
        batch_uid = str(payload.get("batch_uid", ""))
        latest = latest_preview(config, batch_uid)
        reverse = {
            "operations": [
                {"old_path": op["new_path"], "new_path": op["old_path"]}
                for op in latest.get("operations", [])
                if isinstance(op, dict) and op.get("old_path") and op.get("new_path")
            ]
        }
        preview_path(config, batch_uid, rollback_uid).write_text(json.dumps(reverse, indent=2), encoding="utf-8")
        return f"Apercu d'annulation cree. batch_uid={batch_uid} preview_uid={rollback_uid}"
    if command_type == "photo.rename.rollback_execute":
        preview = read_preview(config, payload)
        apply_operations(preview.get("operations", []))
        return f"Annulation terminee pour {len(preview.get('operations', []))} fichier(s)."
    raise RuntimeError(f"Unsupported command: {command_type}")


def resolve_photo_folder(config: dict, payload: dict) -> Path:
    root_uid = str(payload.get("root_uid", ""))
    relative_dir = str(payload.get("relative_dir", "")).replace("\\", "/").strip("/")
    roots = config.get("allowed_roots") if isinstance(config.get("allowed_roots"), list) else []
    root_path = None
    for root in roots:
        if isinstance(root, dict) and str(root.get("uid")) == root_uid:
            root_path = Path(str(root.get("path", ""))).expanduser().resolve()
            break
    if root_path is None:
        raise RuntimeError("Unknown local root")
    target = (root_path / relative_dir).resolve()
    if root_path != target and root_path not in target.parents:
        raise RuntimeError("Path escapes local root")
    if not target.is_dir():
        raise RuntimeError("Local folder not found")
    return target


def build_rename_preview(config: dict, payload: dict, preview_uid: str) -> dict:
    folder = resolve_photo_folder(config, payload)
    names = [str(item) for item in payload.get("items", []) if isinstance(item, str)]
    operations = []
    counter = 1
    for name in names:
        source = (folder / name).resolve()
        if folder not in source.parents or not source.is_file() or source.suffix.lower() not in PHOTO_EXTENSIONS:
            raise RuntimeError(f"Invalid selected photo: {name}")
        new_name = local_filename(payload, source, counter)
        target = source.with_name(new_name)
        operations.append({"old_path": str(source), "new_path": str(target)})
        counter += 1
    return {
        "preview_uid": preview_uid,
        "batch_uid": str(payload.get("batch_uid", "")),
        "created_at": utc_now(),
        "operations": operations,
    }


def local_filename(payload: dict, source: Path, counter: int) -> str:
    prefix = ""
    suffix = ""
    for block in payload.get("template", []):
        if not isinstance(block, dict):
            continue
        if block.get("type") == "text" and not prefix:
            prefix = safe_part(str(block.get("value", "")))
        elif block.get("type") == "text":
            suffix = safe_part(str(block.get("value", "")))
    separator = str(payload.get("separator", "-"))
    if separator not in {"-", "_", " "}:
        separator = "-"
    digits = int(payload.get("counter_digits", 3) or 3)
    parts = [p for p in [prefix, source.stem, str(counter).zfill(max(1, min(6, digits))), suffix] if p]
    return safe_part(separator.join(parts)) + source.suffix.lower()


def safe_part(value: str) -> str:
    forbidden = '<>:"/\\|?*'
    cleaned = "".join("_" if char in forbidden or ord(char) < 32 else char for char in value.strip())
    cleaned = " ".join(cleaned.split())
    return cleaned[:150] or "photo"


def preview_path(config: dict, batch_uid: str, preview_uid: str) -> Path:
    root = data_root(config) / "previews"
    root.mkdir(parents=True, exist_ok=True)
    safe_batch = "".join(ch for ch in batch_uid if ch in "0123456789abcdef")[:32] or "batch"
    safe_preview = "".join(ch for ch in preview_uid if ch in "0123456789abcdef")[:32] or "preview"
    return root / f"{safe_batch}-{safe_preview}.json"


def latest_preview(config: dict, batch_uid: str) -> dict:
    root = data_root(config) / "previews"
    safe_batch = "".join(ch for ch in batch_uid if ch in "0123456789abcdef")[:32] or "batch"
    matches = sorted(root.glob(f"{safe_batch}-*.json"), key=lambda path: path.stat().st_mtime, reverse=True)
    if not matches:
        raise RuntimeError("No preview found for batch")
    return json.loads(matches[0].read_text(encoding="utf-8"))


def read_preview(config: dict, payload: dict) -> dict:
    path = preview_path(config, str(payload.get("batch_uid", "")), str(payload.get("preview_uid", "")))
    if not path.is_file():
        raise RuntimeError("Preview not found")
    return json.loads(path.read_text(encoding="utf-8"))


def apply_operations(operations: list) -> None:
    temp_paths = []
    for index, op in enumerate(operations):
        old_path = Path(str(op["old_path"]))
        new_path = Path(str(op["new_path"]))
        if not old_path.is_file():
            raise RuntimeError(f"Missing file: {old_path.name}")
        if new_path.exists() and new_path.resolve() != old_path.resolve():
            raise RuntimeError(f"Target exists: {new_path.name}")
        temp = old_path.with_name(f".pbgestion-{uuid.uuid4().hex}-{index}.tmp{old_path.suffix}")
        old_path.rename(temp)
        temp_paths.append((temp, new_path))
    for temp, target in temp_paths:
        temp.rename(target)


def data_root(config: dict) -> Path:
    root = Path(str(config.get("data_root", ""))).expanduser()
    if not str(root):
        root = Path.home() / "AppData" / "Local" / "pbgestion" / "agent"
    root.mkdir(parents=True, exist_ok=True)
    return root


def log_local(config: dict, entry: dict) -> None:
    log_path = data_root(config) / "pbgestion-agent.log"
    entry["logged_at"] = utc_now()
    with log_path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(entry, ensure_ascii=False, sort_keys=True) + "\n")


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=["enroll", "run-once"])
    parser.add_argument("--config", required=True)
    args = parser.parse_args(argv)
    config_path = Path(args.config).expanduser()
    if args.command == "enroll":
        enroll(config_path)
    else:
        run_once(config_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
