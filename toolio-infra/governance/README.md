# Governance — Single Source of Truth

Dieser Ordner ist die **einzige** gepflegte Quelle für die projektweiten Governance-Dateien.
Toolio ist ein Multi-Repo; damit externe Klone dieselben Regeln erhalten, werden diese
Dateien in **jedes** Repo gespiegelt.

## Dateien

| Quelle | Rolle | Zielort in jedem Repo |
|---|---|---|
| `copilot-instructions.md` | Technische Hard Rules + Terminologie (KI, auto-geladen) | `.github/copilot-instructions.md` |
| `AGENTS.md` | Arbeitsweise für KI-Agenten (auto-geladen) | `AGENTS.md` (Repo-Root) |
| `CONTRIBUTING.md` | Beitragsprozess für Menschen | `CONTRIBUTING.md` (Repo-Root) |

Nicht gespiegelt (nur in `toolio-infra`, per Link referenziert):
- [`docs/00-engineering-charter.md`](../docs/00-engineering-charter.md) — Identität/Philosophie
- [`docs/adr/`](../docs/adr/) — Architekturentscheidungen

## Regeln

1. **Nie eine Kopie editieren.** Kopien tragen einen `GENERATED — DO NOT EDIT`-Banner.
2. Änderungen immer hier in `governance/` vornehmen.
3. Danach spiegeln:
   ```powershell
   ./sync-governance.ps1
   ```
4. Drift prüfen (CI):
   ```powershell
   ./sync-governance.ps1 -Check   # Exit 1 bei Abweichung
   ```
5. Kopien in allen Repos committen und pushen.

## Warum spiegeln statt verlinken?

Wer nur `mod_toolio` klont, hat `toolio-infra` nicht daneben. Nur physisch vorhandene
`.github/copilot-instructions.md` / `AGENTS.md` werden von den KI-Tools geladen. Deshalb:
eine Quelle, automatisch verteilte Kopien. Doku-Links in den Kopien sind **absolute
GitHub-URLs** auf `toolio-infra`, damit sie in jedem Klon funktionieren.
