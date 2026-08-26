<#
.SYNOPSIS
  Spiegelt die zentralen Governance-Dateien aus toolio-infra/governance/ in alle Repos.

.DESCRIPTION
  Single Source of Truth: Nur die Dateien in diesem Ordner werden gepflegt. Dieses Skript
  erzeugt daraus die (schreibgeschützten) Kopien in jedem Toolio-Repository:

    governance/copilot-instructions.md -> <repo>/.github/copilot-instructions.md
    governance/AGENTS.md               -> <repo>/AGENTS.md
    governance/CONTRIBUTING.md         -> <repo>/CONTRIBUTING.md

  Jede Kopie erhaelt einen GENERATED-Banner und darf nicht direkt editiert werden.

.PARAMETER Check
  Aendert nichts. Prueft nur, ob Kopien von der Quelle abweichen (Drift). Exit-Code 1 bei
  Abweichung -> fuer CI geeignet.

.EXAMPLE
  ./sync-governance.ps1           # schreibt/aktualisiert alle Kopien
  ./sync-governance.ps1 -Check    # nur pruefen (CI)
#>
[CmdletBinding()]
param(
    [switch]$Check
)

$ErrorActionPreference = 'Stop'

# governance/ -> toolio-infra -> Workspace-Root
$governanceDir = $PSScriptRoot
$workspaceRoot = Split-Path -Parent (Split-Path -Parent $governanceDir)

# Zielrepos (relativ zum Workspace-Root). '.' = Workspace-Umbrella.
# format_tiles hat externen Ursprung -> bei Bedarf entfernen.
$targets = @(
    '.',
    'toolio-infra',
    'plugins/mod_toolio',
    'plugins/block_toolio',
    'plugins/mod_bewertung',
    'plugins/mod_abfragetool',
    'plugins/mod_kichatbot',
    'plugins/mod_kollabboard',
    'plugins/format_tiles'
)

# Quelle -> Zielpfad im Repo
$fileMap = [ordered]@{
    'copilot-instructions.md' = '.github/copilot-instructions.md'
    'AGENTS.md'               = 'AGENTS.md'
    'CONTRIBUTING.md'         = 'CONTRIBUTING.md'
}

function Get-Banner([string]$sourceName) {
    return @"
<!-- =============================================================
     GENERATED FILE — DO NOT EDIT.
     Quelle: toolio-infra/governance/$sourceName
     Aendern nur in der Quelle, dann governance/sync-governance.ps1 ausfuehren.
     ============================================================= -->
"@
}

function Get-Expected([string]$sourceName) {
    $srcPath = Join-Path $governanceDir $sourceName
    $content = [IO.File]::ReadAllText($srcPath)
    $text = (Get-Banner $sourceName) + "`n`n" + $content
    return ($text -replace "`r`n", "`n")   # auf LF normalisieren
}

$drift = @()
$written = 0

foreach ($repo in $targets) {
    foreach ($src in $fileMap.Keys) {
        $expected = Get-Expected $src
        $destRel  = $fileMap[$src]
        $destPath = Join-Path (Join-Path $workspaceRoot $repo) $destRel

        if ($Check) {
            if (-not (Test-Path $destPath)) {
                $drift += "FEHLT:  $repo/$destRel"
                continue
            }
            $actual = ([IO.File]::ReadAllText($destPath)) -replace "`r`n", "`n"
            if ($actual -ne $expected) { $drift += "DRIFT:  $repo/$destRel" }
        }
        else {
            $destDir = Split-Path -Parent $destPath
            if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
            $utf8NoBom = [System.Text.UTF8Encoding]::new($false)
            [IO.File]::WriteAllText($destPath, $expected, $utf8NoBom)
            Write-Host "geschrieben: $repo/$destRel"
            $written++
        }
    }
}

if ($Check) {
    if ($drift.Count -gt 0) {
        Write-Host "Governance-Drift erkannt:" -ForegroundColor Red
        $drift | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
        Write-Host "-> governance/sync-governance.ps1 ausfuehren und committen." -ForegroundColor Yellow
        exit 1
    }
    Write-Host "Keine Abweichung. Alle Kopien sind synchron." -ForegroundColor Green
}
else {
    Write-Host "Fertig. $written Datei(en) aktualisiert." -ForegroundColor Green
}
