#!/usr/bin/env python3
"""Run standard benchmarks against a FireLam checkpoint via lm-evaluation-harness.

This is what produces the numbers that belong in docs/BENCHMARKS.md — never
hand-write those numbers.

Usage:
    python src/evaluate.py --model outputs/firelam-1.5-merged --suite humaneval,gsm8k,mmlu
    python src/evaluate.py --model outputs/firelam-1.5-merged --suite humaneval --baseline Qwen/Qwen2.5-Coder-7B-Instruct

Requires: pip install lm-eval (already in requirements.txt). Coding benchmarks
(HumanEval/MBPP) additionally need the bigcode-evaluation-harness for code
execution sandboxing — see docs/TRAINING.md for setup.
"""
import argparse
import json
import subprocess
import sys
from datetime import datetime, timezone


def run_lm_eval(model_path: str, tasks: str, out_path: str):
    cmd = [
        sys.executable, "-m", "lm_eval",
        "--model", "hf",
        "--model_args", f"pretrained={model_path},dtype=bfloat16",
        "--tasks", tasks,
        "--batch_size", "auto",
        "--output_path", out_path,
    ]
    print("Running:", " ".join(cmd))
    subprocess.run(cmd, check=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", required=True, help="Path or HF id of model to evaluate")
    parser.add_argument(
        "--suite", default="gsm8k,mmlu",
        help="Comma-separated lm-eval task names. Coding tasks (humaneval, mbpp) need "
             "the bigcode harness — see docs/TRAINING.md.",
    )
    parser.add_argument(
        "--baseline", default=None,
        help="Optional second model (e.g. the un-fine-tuned base) to eval for comparison",
    )
    args = parser.parse_args()

    timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    results_dir = f"outputs/eval-results-{timestamp}"

    run_lm_eval(args.model, args.suite, f"{results_dir}/firelam")
    if args.baseline:
        run_lm_eval(args.baseline, args.suite, f"{results_dir}/baseline")

    print(f"\nResults written to {results_dir}/")
    print("Copy the summarized scores into docs/BENCHMARKS.md with the run date and command used.")
    print("Do not publish capability claims that aren't backed by a results file in this repo.")


if __name__ == "__main__":
    main()
