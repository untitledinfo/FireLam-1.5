# Hardware requirements

These are **QLoRA training** estimates (4-bit base + LoRA adapters), the cheapest way
to fine-tune. Full-precision fine-tuning needs far more VRAM and isn't covered here.

| Model size | Min VRAM (QLoRA, batch=2, seq=4096) | Recommended GPU | Training time* |
|---|---|---|---|
| 1.5B | ~8 GB | RTX 3060 12GB, RTX 4060 Ti | Hours, on a few thousand examples |
| 3B | ~12 GB | RTX 3060 12GB, RTX 4070 | Hours–half a day |
| 7B | ~24 GB | RTX 3090/4090, A10, A100 (cloud) | Half a day–a day |

\* Wildly dependent on dataset size, epochs, and sequence length — these are ballpark
figures for a few thousand to tens of thousands of examples, not a guarantee.

## No local GPU?

Cloud options that work fine with this repo as-is:
- **Google Colab** (free tier: T4, 16GB — works for 1.5B/3B QLoRA)
- **Lambda Labs / RunPod / Vast.ai** — rent A10/A100/4090 by the hour
- **Kaggle notebooks** — free T4/P100, similar to Colab

## Inference / running the final model

Once merged and converted to GGUF, running FireLam via Ollama is much lighter than
training:

| Quantization | Approx. size (7B) | Min RAM/VRAM to run |
|---|---|---|
| Q4_K_M | ~4.4 GB | 8 GB RAM (CPU) or 6 GB VRAM |
| Q5_K_M | ~5.1 GB | 10 GB RAM or 8 GB VRAM |
| Q8_0 | ~7.5 GB | 16 GB RAM or 10 GB VRAM |

This is why FireLam is distributed as GGUF for end users — training needs a real GPU,
running it afterward doesn't.
