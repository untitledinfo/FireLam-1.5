# Benchmarks

**No results yet.** This file is populated by running `src/evaluate.py` against
a real FireLam checkpoint — do not fill in numbers by hand or estimate.

Once you've trained and evaluated a checkpoint, add a row per run:

| Date | Model | Base model | Task | Score | Baseline score | Command |
|---|---|---|---|---|---|---|
| _(none yet)_ | | | | | | |

## How to fill this in

```bash
python src/evaluate.py --model outputs/firelam-1.5-merged \
  --suite humaneval,mbpp,gsm8k,mmlu \
  --baseline Qwen/Qwen2.5-Coder-7B-Instruct
```

Copy the resulting scores here alongside the baseline (un-fine-tuned base model)
score run with the identical command, so the comparison is apples-to-apples.

## Comparing against other models (e.g. Ollama's default models)

"Ollama" is a runtime, not a model — there's no single "Ollama" score to beat.
If you want a comparison claim like "FireLam-7B beats Llama-3.1-8B-Instruct on
HumanEval," run the same `evaluate.py` command against that specific model and
put both rows in this table. Only publish comparisons you can point to a row
in this file for.
