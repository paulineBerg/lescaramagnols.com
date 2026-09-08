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
import platform
import struct
import sys
import uuid
from urllib import request, error

try:
    from nacl.signing import SigningKey
except Exception as exc:  # pragma: no cover - installer handles dependency
    print("PyNaCl is required: python -m pip install pynacl", file=sys.stderr)
    raise SystemExit(2) from exc


VERSION = "1.0.0"
PHOTO_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp", ".heic"}
PHOTO_CAPABILITIES = [
    "photos",
    "photo_geo_renamer_v2",
    "photo.exif.date",
    "photo.exif.gps",
    "photo.rename.preview",
    "photo.rename.execute",
    "photo.rename.results",
]


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
        with request.urlopen(req, timeout=60) as response:
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
        "os_family": os_family(),
        "os_version": platform_label(),
        "agent_version": VERSION,
        "capabilities": PHOTO_CAPABILITIES,
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
    return f"{platform.system()} {platform.release()}".strip()


def os_family() -> str:
    if os.name == "nt":
        return "windows"
    system = platform.system().lower()
    if system == "darwin":
        return "macos"
    if system == "linux":
        return "linux"

    return (system or sys.platform).lower()[:32]


def run_once(config_path: Path) -> None:
    config = read_json(config_path)
    flush_outbox(config)
    commands = signed_post(config, "/api/pbgestion/v1/commands/poll", {}).get("commands", [])
    write_json(config_path, config)
    for command in commands:
        if not isinstance(command, dict):
            continue
        handle_command(config_path, command)
    config = read_json(config_path)
    flush_outbox(config)
    write_json(config_path, config)


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
        enqueue_outbox(config, "photo_rename_previews", preview)
        try_flush_outbox(config)
        return f"Apercu cree. batch_uid={payload.get('batch_uid')} preview_uid={preview_uid}"
    if command_type == "photo.rename.execute":
        result = execute_rename(config, payload)
        enqueue_outbox(config, "photo_rename_results", result)
        try_flush_outbox(config)
        return result_message("Renommage termine", result)
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
        result = apply_legacy_operations(str(payload.get("batch_uid", "")), str(payload.get("preview_uid", "")), preview.get("operations", []))
        enqueue_outbox(config, "photo_rename_results", result)
        try_flush_outbox(config)
        return result_message("Annulation terminee", result)
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
        relative_path = normalize_relative_photo(name)
        if relative_path is None:
            raise RuntimeError(f"Invalid selected photo: {Path(name).name}")
        source = resolve_child(folder, relative_path)
        if folder not in source.parents or not source.is_file() or source.suffix.lower() not in PHOTO_EXTENSIONS:
            raise RuntimeError(f"Invalid selected photo: {name}")
        metadata = extract_photo_metadata(source)
        new_name = local_filename(payload, source, counter)
        target = source.with_name(new_name)
        operation = {
            "file_uid": local_file_uid(source, relative_path),
            "relative_path": relative_path,
            "old_name": source.name,
            "new_name": target.name,
            "taken_at": metadata.get("taken_at"),
            "taken_at_source": metadata.get("taken_at_source"),
            "latitude": metadata.get("latitude"),
            "longitude": metadata.get("longitude"),
        }
        operations.append({key: value for key, value in operation.items() if value is not None and value != ""})
        counter += 1
    return {
        "preview_uid": preview_uid,
        "batch_uid": str(payload.get("batch_uid", "")),
        "root_uid": str(payload.get("root_uid", "")),
        "relative_dir": str(payload.get("relative_dir", "")).replace("\\", "/").strip("/"),
        "template": payload.get("template", []),
        "separator": str(payload.get("separator", "-")),
        "counter_digits": int(payload.get("counter_digits", 2) or 2),
        "sort_order": str(payload.get("sort_order", "chronological")),
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


def normalize_relative_photo(value: str) -> str | None:
    value = value.replace("\\", "/").strip()
    if not value or value.startswith("/") or value.startswith("//"):
        return None
    if len(value) >= 2 and value[1] == ":" and value[0].isalpha():
        return None
    forbidden = '<>:"|?*'
    segments = []
    for segment in value.split("/"):
        segment = segment.strip()
        if not segment or segment in {".", ".."} or segment.strip(". ") != segment:
            return None
        if any(char in forbidden or ord(char) < 32 for char in segment):
            return None
        segments.append(segment)
    if Path(segments[-1]).suffix.lower() not in PHOTO_EXTENSIONS:
        return None
    return "/".join(segments)


def resolve_child(base: Path, relative_path: str) -> Path:
    child = (base / relative_path).resolve()
    if child != base and base not in child.parents:
        raise RuntimeError("Path escapes local root")
    return child


def local_file_uid(path: Path, relative_path: str) -> str:
    stat = path.stat()
    sample = f"{relative_path}|{stat.st_size}|{stat.st_mtime_ns}"
    return hashlib.sha256(sample.encode("utf-8")).hexdigest()[:32]


def extract_photo_metadata(path: Path) -> dict:
    if path.suffix.lower() not in {".jpg", ".jpeg"}:
        return {}
    try:
        exif = read_jpeg_exif(path)
    except Exception as exc:
        return {"metadata_error": exc.__class__.__name__}
    if not exif:
        return {}

    metadata = {}
    taken_at = exif.get("taken_at")
    if isinstance(taken_at, str) and taken_at:
        metadata["taken_at"] = taken_at
        metadata["taken_at_source"] = "exif"
    latitude = exif.get("latitude")
    longitude = exif.get("longitude")
    if isinstance(latitude, float) and isinstance(longitude, float):
        metadata["latitude"] = latitude
        metadata["longitude"] = longitude
    return metadata


def read_jpeg_exif(path: Path) -> dict:
    data = path.read_bytes()
    if len(data) < 4 or data[:2] != b"\xff\xd8":
        return {}
    offset = 2
    while offset + 4 <= len(data):
        if data[offset] != 0xFF:
            break
        marker = data[offset + 1]
        offset += 2
        if marker in {0xD9, 0xDA} or offset + 2 > len(data):
            break
        length = int.from_bytes(data[offset:offset + 2], "big")
        segment_start = offset + 2
        segment_end = offset + length
        if length < 2 or segment_end > len(data):
            break
        segment = data[segment_start:segment_end]
        if marker == 0xE1 and segment.startswith(b"Exif\x00\x00"):
            return parse_tiff_exif(segment[6:])
        offset = segment_end
    return {}


def parse_tiff_exif(tiff: bytes) -> dict:
    if len(tiff) < 8:
        return {}
    if tiff[:2] == b"II":
        endian = "<"
    elif tiff[:2] == b"MM":
        endian = ">"
    else:
        return {}
    if unpack_short(tiff, 2, endian) != 42:
        return {}

    tags = read_ifd(tiff, unpack_long(tiff, 4, endian), endian)
    exif_tags = read_ifd(tiff, int_value(tags.get(0x8769)), endian) if 0x8769 in tags else {}
    gps_tags = read_ifd(tiff, int_value(tags.get(0x8825)), endian) if 0x8825 in tags else {}
    result = {}
    date_taken = ascii_value(exif_tags.get(0x9003) or exif_tags.get(0x9004) or tags.get(0x0132))
    if date_taken:
        result["taken_at"] = normalize_exif_datetime(date_taken)

    latitude = gps_coordinate(gps_tags.get(0x0002), ascii_value(gps_tags.get(0x0001)))
    longitude = gps_coordinate(gps_tags.get(0x0004), ascii_value(gps_tags.get(0x0003)))
    if latitude is not None and longitude is not None:
        result["latitude"] = latitude
        result["longitude"] = longitude
    return result


def read_ifd(tiff: bytes, offset: int, endian: str) -> dict:
    if offset <= 0 or offset + 2 > len(tiff):
        return {}
    count = unpack_short(tiff, offset, endian)
    entries = {}
    cursor = offset + 2
    for _ in range(min(count, 256)):
        if cursor + 12 > len(tiff):
            break
        tag = unpack_short(tiff, cursor, endian)
        field_type = unpack_short(tiff, cursor + 2, endian)
        value_count = unpack_long(tiff, cursor + 4, endian)
        value_or_offset = tiff[cursor + 8:cursor + 12]
        value_size = type_size(field_type) * value_count
        if value_size <= 4:
            raw = value_or_offset[:value_size]
        else:
            value_offset = unpack_long(tiff, cursor + 8, endian)
            raw = tiff[value_offset:value_offset + value_size] if 0 <= value_offset <= len(tiff) else b""
        entries[tag] = (field_type, value_count, raw, endian)
        cursor += 12
    return entries


def type_size(field_type: int) -> int:
    return {1: 1, 2: 1, 3: 2, 4: 4, 5: 8, 7: 1, 9: 4, 10: 8}.get(field_type, 0)


def unpack_short(data: bytes, offset: int, endian: str) -> int:
    return struct.unpack_from(endian + "H", data, offset)[0]


def unpack_long(data: bytes, offset: int, endian: str) -> int:
    return struct.unpack_from(endian + "L", data, offset)[0]


def int_value(entry: tuple | None) -> int:
    if not entry:
        return 0
    field_type, _count, raw, endian = entry
    if field_type == 3 and len(raw) >= 2:
        return struct.unpack_from(endian + "H", raw, 0)[0]
    if field_type == 4 and len(raw) >= 4:
        return struct.unpack_from(endian + "L", raw, 0)[0]
    return 0


def ascii_value(entry: tuple | None) -> str | None:
    if not entry:
        return None
    field_type, _count, raw, _endian = entry
    if field_type != 2:
        return None
    return raw.split(b"\x00", 1)[0].decode("utf-8", errors="replace").strip() or None


def gps_coordinate(entry: tuple | None, ref: str | None) -> float | None:
    if not entry or not ref:
        return None
    field_type, count, raw, endian = entry
    if field_type != 5 or count < 3 or len(raw) < 24:
        return None
    parts = []
    for index in range(3):
        numerator = struct.unpack_from(endian + "L", raw, index * 8)[0]
        denominator = struct.unpack_from(endian + "L", raw, index * 8 + 4)[0]
        if denominator == 0:
            return None
        parts.append(numerator / denominator)
    value = parts[0] + parts[1] / 60 + parts[2] / 3600
    if ref.upper() in {"S", "W"}:
        value = -value
    return round(value, 7)


def normalize_exif_datetime(value: str | None) -> str | None:
    if not value:
        return None
    value = value.strip()
    try:
        parsed = dt.datetime.strptime(value[:19], "%Y:%m:%d %H:%M:%S")
        return parsed.strftime("%Y-%m-%d %H:%M:%S")
    except ValueError:
        return None


def execute_rename(config: dict, payload: dict) -> dict:
    operations = payload.get("operations")
    if isinstance(operations, list) and operations:
        return apply_payload_operations(config, payload, operations)

    preview = read_preview(config, payload)
    return apply_legacy_operations(str(payload.get("batch_uid", "")), str(payload.get("preview_uid", "")), preview.get("operations", []))


def apply_payload_operations(config: dict, payload: dict, operations: list) -> dict:
    folder = resolve_photo_folder(config, payload)
    batch_uid = str(payload.get("batch_uid", ""))
    preview_uid = str(payload.get("preview_uid", ""))
    planned = []
    validation_errors = []
    seen_targets = set()

    for index, operation in enumerate(operations):
        if not isinstance(operation, dict):
            validation_errors.append(result_operation("", "", "", "failed", "invalid_operation", f"Operation invalide #{index + 1}"))
            continue

        relative_path = normalize_relative_photo(str(operation.get("relative_path", "")))
        old_name = Path(str(operation.get("old_name", ""))).name
        new_name = normalize_relative_photo(str(operation.get("new_name", "")))
        temporary_name = str(operation.get("temporary_name", ""))
        if relative_path is None or new_name is None or not valid_temporary_name(temporary_name):
            validation_errors.append(result_operation(relative_path or old_name, old_name, new_name or "", "failed", "invalid_operation", "Operation refusee par la politique locale"))
            continue

        source = resolve_child(folder, relative_path)
        target = resolve_child(folder, new_name)
        temporary = resolve_child(folder, temporary_name)
        target_key = str(target).lower()
        if target_key in seen_targets:
            validation_errors.append(result_operation(relative_path, old_name, new_name, "failed", "duplicate_target", "Destination en double"))
            continue
        seen_targets.add(target_key)

        if not source.is_file():
            validation_errors.append(result_operation(relative_path, old_name, new_name, "failed", "source_missing", "Fichier source introuvable"))
            continue
        if target.exists() and target.resolve() != source.resolve():
            validation_errors.append(result_operation(relative_path, old_name, new_name, "failed", "target_exists", "Destination deja presente"))
            continue
        if temporary.exists():
            validation_errors.append(result_operation(relative_path, old_name, new_name, "failed", "temporary_exists", "Fichier temporaire deja present"))
            continue

        planned.append({
            "relative_path": relative_path,
            "old_name": old_name or source.name,
            "new_name": new_name,
            "source": source,
            "target": target,
            "temporary": temporary,
        })

    if validation_errors:
        return rename_result(batch_uid, preview_uid, validation_errors + [
            result_operation(item["relative_path"], item["old_name"], item["new_name"], "failed", "batch_validation_failed", "Lot non execute car une operation est invalide")
            for item in planned
        ])

    return rename_result(batch_uid, preview_uid, run_two_pass(planned))


def apply_legacy_operations(batch_uid: str, preview_uid: str, operations: list) -> dict:
    planned = []
    for index, operation in enumerate(operations):
        if not isinstance(operation, dict):
            continue
        source = Path(str(operation.get("old_path", ""))).resolve()
        target = Path(str(operation.get("new_path", ""))).resolve()
        if not source.is_file():
            return rename_result(batch_uid, preview_uid, [
                result_operation(source.name, source.name, target.name, "failed", "source_missing", "Fichier source introuvable")
            ])
        temporary = source.with_name(f".pbgestion-{uuid.uuid4().hex}-{index}.tmp{source.suffix}")
        planned.append({
            "relative_path": source.name,
            "old_name": source.name,
            "new_name": target.name,
            "source": source,
            "target": target,
            "temporary": temporary,
        })
    return rename_result(batch_uid, preview_uid, run_two_pass(planned))


def run_two_pass(planned: list) -> list:
    results = []
    moved = []
    for item in planned:
        source = item["source"]
        target = item["target"]
        if source.resolve() == target.resolve():
            results.append(result_operation(item["relative_path"], item["old_name"], item["new_name"], "unchanged", "", "Nom deja conforme"))
            continue
        try:
            source.rename(item["temporary"])
            moved.append(item)
        except Exception as exc:
            for previous in reversed(moved):
                try:
                    if previous["temporary"].exists() and not previous["source"].exists():
                        previous["temporary"].rename(previous["source"])
                except Exception:
                    pass
            return [
                result_operation(item["relative_path"], item["old_name"], item["new_name"], "failed", "temporary_rename_failed", short_error(exc))
                if candidate is item else
                result_operation(candidate["relative_path"], candidate["old_name"], candidate["new_name"], "failed", "batch_rollback", "Lot annule avant finalisation")
                for candidate in planned
            ]

    completed_sources = {item["relative_path"] for item in results if item.get("status") == "unchanged"}
    for item in moved:
        try:
            item["temporary"].rename(item["target"])
            results.append(result_operation(item["relative_path"], item["old_name"], item["new_name"], "completed", "", "Renommage effectue"))
        except Exception as exc:
            try:
                if item["temporary"].exists() and not item["source"].exists():
                    item["temporary"].rename(item["source"])
            except Exception:
                pass
            results.append(result_operation(item["relative_path"], item["old_name"], item["new_name"], "failed", "final_rename_failed", short_error(exc)))
    return sorted(results, key=lambda item: (str(item.get("relative_path", "")) not in completed_sources, str(item.get("relative_path", ""))))


def result_operation(relative_path: str, old_name: str, new_name: str, status: str, code: str, message: str) -> dict:
    result = {
        "relative_path": relative_path,
        "old_name": old_name,
        "new_name": new_name,
        "status": status,
    }
    if code:
        result["error_code"] = code
    if message and status == "failed":
        result["error_message"] = message[:220]
    return result


def rename_result(batch_uid: str, preview_uid: str, operations: list) -> dict:
    return {
        "batch_uid": batch_uid,
        "preview_uid": preview_uid,
        "completed_at": utc_now(),
        "operations": operations,
        "success_files": sum(1 for item in operations if item.get("status") in {"completed", "unchanged"}),
        "failed_files": sum(1 for item in operations if item.get("status") == "failed"),
    }


def result_message(prefix: str, result: dict) -> str:
    success = int(result.get("success_files", 0) or 0)
    failed = int(result.get("failed_files", 0) or 0)
    return f"{prefix}: {success} fichier(s) ok, {failed} echec(s)."


def short_error(exc: Exception) -> str:
    return str(exc).replace("\n", " ")[:220] or exc.__class__.__name__


def valid_temporary_name(value: str) -> bool:
    if "/" in value or "\\" in value or not value.startswith(".pbgestion-"):
        return False
    if len(value) > 120 or Path(value).suffix.lower() not in PHOTO_EXTENSIONS:
        return False

    return not any(char in '<>:"|?*' or ord(char) < 32 for char in value)


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


def outbox_root(config: dict, section: str) -> Path:
    root = data_root(config) / "outbox" / section
    root.mkdir(parents=True, exist_ok=True)
    return root


def enqueue_outbox(config: dict, section: str, payload: dict) -> None:
    path = outbox_root(config, section) / f"{utc_now().replace(':', '').replace('-', '')}-{uuid.uuid4().hex}.json"
    path.write_text(json.dumps(payload, ensure_ascii=False, sort_keys=True), encoding="utf-8")


def collect_outbox(config: dict, section: str, limit: int = 5) -> tuple[list, list[Path]]:
    paths = sorted(outbox_root(config, section).glob("*.json"))[:limit]
    items = []
    kept_paths = []
    for path in paths:
        try:
            value = json.loads(path.read_text(encoding="utf-8"))
        except Exception:
            path.unlink(missing_ok=True)
            continue
        if isinstance(value, dict):
            items.append(value)
            kept_paths.append(path)
    return items, kept_paths


def flush_outbox(config: dict) -> None:
    previews, preview_paths = collect_outbox(config, "photo_rename_previews")
    results, result_paths = collect_outbox(config, "photo_rename_results")
    payload = {
        "agent_version": VERSION,
        "os_family": os_family(),
        "os_version": platform_label(),
        "capabilities": PHOTO_CAPABILITIES,
    }
    if previews:
        payload["photo_rename_previews"] = previews
    if results:
        payload["photo_rename_results"] = results

    signed_post(config, "/api/pbgestion/v1/sync", payload)
    for path in preview_paths + result_paths:
        path.unlink(missing_ok=True)


def try_flush_outbox(config: dict) -> None:
    try:
        flush_outbox(config)
    except Exception as exc:
        log_local(config, {"event": "outbox.flush.failed", "error": short_error(exc)})


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
