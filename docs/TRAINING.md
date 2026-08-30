# Training guide

## 1. Pick a base model

Edit `configs/model_config.yaml`. Default is `Qwen/Qwen2.5-Coder-7B-Instruct`
(Apache-2.0, strong at code, fully redistributable). Swap in a 1.5B/3B variant
if your GPU is smaller — see `docs/HARDWARE.md`.

## 2. Build your dataset

Follow `data/README.md`'s format. Aim for at least a few thousand high-quality
examples for a noticeable fine-tune effect; tens of thousands is better if you
have the data. Validate before training:

```bash
python data/validate_dataset.py --path data/your_dataset.jsonl
```

## 3. Configure LoRA and training hyperparameters

`configs/lora_config.yaml` and `configs/training_config.yaml` ship with sane
QLoRA defaults. Things worth tuning first:
- `r` (LoRA rank) — 16 is a good default; try 32/64 if you have VRAM headroom
  and the model still underfits.
- `learning_rate` — 2e-4 is typical for LoRA; lower it (1e-4) if loss spikes.
- `num_train_epochs` — 2-3 for a few-thousand-example dataset; more risks
  overfitting on small data.

## 4. Train

```bash
python src/train.py --config configs/training_config.yaml
```

Watch the loss curve (via `wandb` if `report_to: wandb`, or console logs
otherwise). A LoRA adapter (a few hundred MB, not the full model) lands in
`outputs/firelam-1.5-lora`.

## 5. Merge, convert, package

```bash
python src/merge_lora.py --base_model <base> --adapter outputs/firelam-1.5-lora --out outputs/firelam-1.5-merged
bash scripts/export_model.sh outputs/firelam-1.5-merged firelam-1.5
ollama create firelam-1.5 -f ollama/Modelfile
```

## 6. Evaluate before claiming anything

```bash
python src/evaluate.py --model outputs/firelam-1.5-merged --suite gsm8k,mmlu --baseline <base_model>
```

For coding benchmarks (HumanEval, MBPP), use the
[bigcode-evaluation-harness](https://github.com/bigcode-project/bigcode-evaluation-harness)
instead of plain `lm-eval` — it sandboxes code execution, which `lm-eval` alone
doesn't do safely.

Record every result — model, task, score, date, exact command — in
`docs/BENCHMARKS.md`. That log is what turns "we think it's better" into a
claim you can actually stand behind.

## 7. Publish

- Push the repo (code + configs + dataset card, **not** raw weights) to GitHub.
- Push weights to Hugging Face Hub as a separate model repo, with a model card
  listing base model, license, training data description, and benchmark table.
- Cross-link both repos in each README.
