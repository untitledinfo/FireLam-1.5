#!/usr/bin/env python3
"""Validate a FireLam training dataset (JSONL, chat format).

Usage:
    python data/validate_dataset.py --path data/sample_dataset.jsonl [--max_seq_length 4096]
"""
import argparse
import json
import sys

VALID_ROLES = {"system", "user", "assistant"}


def validate_line(obj: dict, line_no: int) -> list[str]:
    errors = []
    if "messages" not in obj or not isinstance(obj["messages"], list):
        return [f"line {line_no}: missing or invalid 'messages' array"]

    messages = obj["messages"]
    if not messages:
        return [f"line {line_no}: empty messages array"]

    expect_start = 0
    if messages[0]["role"] == "system":
        expect_start = 1

    prev_role = "system" if expect_start else None
    for i, msg in enumerate(messages):
        role = msg.get("role")
        content = msg.get("content", "")

        if role not in VALID_ROLES:
            errors.append(f"line {line_no}, msg {i}: invalid role '{role}'")
        if not content or not content.strip():
            errors.append(f"line {line_no}, msg {i}: empty content")

        if i >= expect_start and role == prev_role and role != "system":
            errors.append(
                f"line {line_no}, msg {i}: role '{role}' repeats consecutively "
                "(expected alternating user/assistant)"
            )
        prev_role = role

    if messages[-1]["role"] != "assistant":
        errors.append(f"line {line_no}: conversation should end on an 'assistant' turn")

    return errors


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--path", required=True, help="Path to JSONL dataset")
    parser.add_argument(
        "--max_seq_length", type=int, default=4096,
        help="Flag examples whose rough token estimate exceeds this",
    )
    args = parser.parse_args()

    total = 0
    all_errors = []
    long_examples = 0

    with open(args.path, "r", encoding="utf-8") as f:
        for line_no, line in enumerate(f, start=1):
            line = line.strip()
            if not line:
                continue
            total += 1
            try:
                obj = json.loads(line)
            except json.JSONDecodeError as e:
                all_errors.append(f"line {line_no}: invalid JSON ({e})")
                continue

            all_errors.extend(validate_line(obj, line_no))

            # Rough token estimate: ~4 chars/token (good enough for a length-flag pass)
            char_count = sum(len(m.get("content", "")) for m in obj.get("messages", []))
            if char_count / 4 > args.max_seq_length:
                long_examples += 1

    print(f"Checked {total} examples from {args.path}")
    print(f"Examples possibly exceeding max_seq_length ({args.max_seq_length}): {long_examples}")

    if all_errors:
        print(f"\nFound {len(all_errors)} problem(s):")
        for err in all_errors[:50]:
            print(f"  - {err}")
        if len(all_errors) > 50:
            print(f"  ... and {len(all_errors) - 50} more")
        sys.exit(1)
    else:
        print("\nDataset looks valid.")


if __name__ == "__main__":
    main()
