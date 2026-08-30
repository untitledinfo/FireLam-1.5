# FireLam 1.5

FireLam 1.5 is an open-source, fine-tuned language model specialized in **coding, debugging,
mathematical reasoning, instruction following, and general conversation**. It is built on top
of a strong open base model using **LoRA/QLoRA** fine-tuning, and is designed to run locally
via **Ollama (GGUF)** or through the Hugging Face `transformers` + `PEFT` stack.

> **Honesty policy**: This repo does not ship or advertise unverified performance claims
> (e.g. "X% smarter than Y"). All capability claims must be backed by numbers in
> [`docs/BENCHMARKS.md`](docs/BENCHMARKS.md), produced by the evaluation scripts in `src/evaluate.py`.
> Until you've run those evals on your own fine-tuned checkpoint, describe FireLam as
> "a fine-tune of `<base model>`, specialized for coding and reasoning" — not as beating
> a specific competitor.

## Versions

| Version | Base model (suggested) | Params | Min GPU (QLoRA train) | Use case |
|---|---|---|---|---|
| FireLam-1.5B | Qwen2.5-Coder-1.5B-Instruct | 1.5B | 8 GB VRAM | Laptops, edge devices |
| FireLam-3B | Qwen2.5-Coder-3B-Instruct | 3B | 12 GB VRAM | Mid-range desktop GPU |
| FireLam-7B | Qwen2.5-Coder-7B-Instruct | 7B | 24 GB VRAM | Workstation / cloud GPU |

Qwen2.5-Coder models are Apache-2.0 licensed (fully permissive: fine-tune, merge, and
redistribute freely). You can swap in another base (DeepSeek-R1-Distill, Llama 3.x, Mistral)
by editing `configs/model_config.yaml` — see [`docs/BASE_MODEL_CHOICES.md`](docs/BASE_MODEL_CHOICES.md)
for licensing notes on each.

## Repo layout

```
FireLam-1.5/
├── configs/            # LoRA, training, and model configs (YAML)
├── data/               # Dataset format spec, sample data, validator
├── src/                # Training, inference, eval, safety-filter, GGUF export code
├── ollama/Modelfile    # Ollama packaging for the merged/quantized model
├── scripts/            # Environment setup + one-shot export pipeline
├── docs/               # Hardware requirements, training guide, benchmark log
└── .github/workflows/  # CI: lint + dataset validation on every push
```

## Quickstart

```bash
# 1. Set up environment
bash scripts/setup_env.sh

# 2. Validate your dataset
python data/validate_dataset.py --path data/sample_dataset.jsonl

# 3. Train (LoRA/QLoRA)
python src/train.py --config configs/training_config.yaml

# 4. Merge LoRA adapter into base weights
python src/merge_lora.py --base_model Qwen/Qwen2.5-Coder-7B-Instruct \
    --adapter outputs/firelam-1.5-lora --out outputs/firelam-1.5-merged

# 5. Convert to GGUF for Ollama
bash scripts/export_model.sh outputs/firelam-1.5-merged firelam-1.5

# 6. Run locally with Ollama
ollama create firelam-1.5 -f ollama/Modelfile
ollama run firelam-1.5

# 7. Evaluate (before making any capability claims)
python src/evaluate.py --model outputs/firelam-1.5-merged --suite humaneval,gsm8k,mmlu
```

See [`docs/HARDWARE.md`](docs/HARDWARE.md) and [`docs/TRAINING.md`](docs/TRAINING.md) for details.

## Deploying a live chat service (VPS + domain + admin panel)

Beyond training, this repo includes everything to run FireLam (or any Ollama
model) as a hosted chat service with your own domain:

```
admin_panel/   FastAPI admin panel + API-key-gated chat endpoint + web chat UI
deploy/        install.sh (one-shot VPS setup), Cloudflare DNS script, nginx +
               systemd configs
```

One command on a fresh VPS installs Ollama, pulls a model, clones this repo,
starts the admin/chat panel as a systemd service, configures nginx, and
(optionally) creates your Cloudflare DNS record and SSL cert automatically.
Full walkthrough: [`deploy/README.md`](deploy/README.md).

## License

Code in this repo: MIT (see `LICENSE`). Model weights inherit the license of whichever base
model you fine-tune (Qwen2.5-Coder = Apache-2.0). You are responsible for complying with your
chosen base model's license when you publish FireLam checkpoints.
