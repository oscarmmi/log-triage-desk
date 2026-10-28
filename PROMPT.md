Build Log Triage Desk in THIS folder: C:\xampp\htdocs\log-triage-desk

A recruiter-ready demo that clusters fictional Harbor Desk error logs and proposes a severity + owner.

Laravel 13 + Vue 3 starter kit is already here if present. Reuse it. Do not create a second app inside NewPortfolio.

## Must satisfy

- Models: `LogEvent` (source, message, occurred_at), `TriageRun` (status), `TriageGroup` (triage_run_id, signature, severity info|warn|error, owner, count, sample_ids json, reasons json).
- Seed 12 fictional log lines: 3 clusters (checkout 500, webhook timeout, benign info) plus 1 unique.
- `App\Services\Logs\LogTriage`: group by normalized signature (strip ids/uuids), TF-IDF nearest neighbor, then optional LLM severity+owner. Timeout 12s. Never 500.
- `App\Services\Llm\LlmClient` with `FakeLlmClient` in tests and when no `GEMINI_API_KEY`. Optional Gemini when key is set.
- UI: run triage, group cards with count/severity/owner, expand samples.
- Pest: checkout 500 lines land in one group with severity error; unique line is its own group; FakeLlm bound; guest POST 200.
- README mermaid: Logs → signature cluster → LLM severity → groups. How to run without API key.

## GitHub

- git config user.name "Oscar Munoz" / user.email "oscarxxi@gmail.com"
- Commit: feat: add log triage desk
- Public https://github.com/oscarmmi/log-triage-desk
- gh repo edit --description "Cluster error logs and assign severity with TF-IDF plus an LLM (Laravel + Vue)." --add-topic laravel --add-topic vue --add-topic ai --add-topic php --add-topic observability
- Stop. Print the URL.

No Impeccable. No Redis. No real production logs.
