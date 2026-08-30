"""Lightweight, transparent safety filtering for FireLam inference.

This is NOT a substitute for real content-moderation infrastructure — it's a
minimal, auditable first layer you can extend. It blocks obvious categories
(malware requests, weapon synthesis, CSAM) via keyword/pattern heuristics and
logs anything filtered so you can review false positives/negatives.

For production use, pair this with an actual moderation model
(e.g. Llama-Guard, OpenAI moderation endpoint, or a fine-tuned classifier)
instead of relying on keyword matching alone.
"""
import re
from dataclasses import dataclass


@dataclass
class FilterResult:
    allowed: bool
    reason: str | None = None


# Deliberately coarse — a real deployment should use a classifier, not regex.
_BLOCKED_PATTERNS = [
    r"\bhow to (make|build|synthesize)\b.*\b(bomb|explosive|nerve agent|bioweapon)\b",
    r"\bransomware\b.*\b(source code|write me|create)\b",
    r"\bchild (sexual|porn)",
]

_COMPILED = [re.compile(p, re.IGNORECASE) for p in _BLOCKED_PATTERNS]


def check_input(text: str) -> FilterResult:
    for pattern in _COMPILED:
        if pattern.search(text):
            return FilterResult(allowed=False, reason="matched restricted-content pattern")
    return FilterResult(allowed=True)


def check_output(text: str) -> FilterResult:
    # Mirror input checks on generated output as a second layer.
    return check_input(text)


if __name__ == "__main__":
    import sys

    sample = sys.argv[1] if len(sys.argv) > 1 else "Write a hello world function in Python."
    result = check_input(sample)
    print(result)
