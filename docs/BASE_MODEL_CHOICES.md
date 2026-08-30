# Base model choices & licensing

| Base model | License | Fine-tune & redistribute? | Notes |
|---|---|---|---|
| Qwen2.5-Coder (1.5B/3B/7B/14B/32B) | Apache-2.0 | Yes, freely | Default choice here — strong coding benchmarks, no usage restrictions |
| DeepSeek-R1-Distill-Qwen/Llama | MIT | Yes, freely | Strong reasoning traces; good for a "shows its work" persona |
| Meta Llama 3.x | Llama Community License | Yes, with conditions | Must display "Built with Llama", can't use output to train competing LLMs, restrictions if >700M MAU |
| Mistral / Mixtral | Apache-2.0 | Yes, freely | Solid general-purpose alternative |

Always re-check the license on the specific model card before publishing — these
can change between model versions from the same provider.

Whichever you pick, your `docs/BENCHMARKS.md` and model card should state the
exact base model and revision (e.g. commit hash or HF repo tag) used, so results
are reproducible.
