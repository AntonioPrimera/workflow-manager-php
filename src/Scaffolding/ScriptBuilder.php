<?php

declare(strict_types=1);

namespace AntonioPrimera\WorkflowManager\Scaffolding;

use Illuminate\Filesystem\Filesystem;

/**
 * Generates shell wrappers for common Workflow Manager API endpoints.
 */
class ScriptBuilder
{
    /**
     * @var array<int, string>
     */
    private const SCRIPTS = [
        'create-project',
        'list-projects',
        'create-workflow',
        'list-workflows',
        'get-workflow',
        'create-step',
        'list-steps',
        'update-step-status',
        'create-document',
        'get-document',
        'update-document',
    ];

    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * Generates or refreshes all CLI wrapper scripts.
     */
    public function generate(string $basePath): void
    {
        $binPath = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.dev-workflow/bin';
        $this->filesystem->ensureDirectoryExists($binPath);

        $sharedPath = $binPath.'/_shared.sh';
        $this->filesystem->put($sharedPath, $this->sharedScript());
        $this->filesystem->chmod($sharedPath, 0755);

        foreach (self::SCRIPTS as $name) {
            $path = $binPath.'/'.$name;
            $this->filesystem->put($path, $this->scriptContent($name));
            $this->filesystem->chmod($path, 0755);
        }
    }

    /**
     * Builds the shared helper script.
     */
    private function sharedScript(): string
    {
        return <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ -f "$(pwd)/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    source "$(pwd)/.env"
    set +a
fi

if [ -z "${WORKFLOW_MANAGER_URL:-}" ] || [ -z "${WORKFLOW_MANAGER_API_KEY:-}" ]; then
    echo "Missing Workflow Manager configuration. Ensure .env contains WORKFLOW_MANAGER_URL and WORKFLOW_MANAGER_API_KEY." >&2
    exit 1
fi

AUTH_HEADER="Authorization: Bearer ${WORKFLOW_MANAGER_API_KEY}"
BASE_URL="${WORKFLOW_MANAGER_URL%/}"
BASH;
    }

    /**
     * Returns script content for the given script name.
     */
    private function scriptContent(string $name): string
    {
        $template = match ($name) {
            'create-project' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

PAYLOAD=${1:-'{}'}

curl -sS \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -X POST "$WORKFLOW_MANAGER_URL/api/wfm/projects" \
  -d "$PAYLOAD"
BASH,
            'list-projects' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "$AUTH_HEADER" \
  "$WORKFLOW_MANAGER_URL/api/wfm/projects"
BASH,
            'create-workflow' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

PAYLOAD=${1:-'{}'}

curl -sS \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -X POST "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows" \
  -d "$PAYLOAD"
BASH,
            'list-workflows' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "$AUTH_HEADER" \
  "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows"
BASH,
            'get-workflow' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: get-workflow <workflow-id>" >&2
    exit 1
fi

WORKFLOW_ID=$1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "$AUTH_HEADER" \
  "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID"
BASH,
            'create-step' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 2 ]; then
    echo "Usage: create-step <workflow-id> '<json-payload>'" >&2
    exit 1
fi

WORKFLOW_ID=$1
PAYLOAD=$2

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -X POST "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID/steps" \
  -d "$PAYLOAD"
BASH,
            'list-steps' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: list-steps <workflow-id>" >&2
    exit 1
fi

WORKFLOW_ID=$1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "$AUTH_HEADER" \
  "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID/steps"
BASH,
            'update-step-status' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 3 ]; then
    echo "Usage: update-step-status <workflow-id> <step-id> '<json-payload>'" >&2
    exit 1
fi

WORKFLOW_ID=$1
STEP_ID=$2
PAYLOAD=$3

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -X PATCH "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID/steps/$STEP_ID/status" \
  -d "$PAYLOAD"
BASH,
            'create-document' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 4 ]; then
    echo "Usage: create-document <workflow-id> <step-id> <filename> '<json-payload>'" >&2
    exit 1
fi

WORKFLOW_ID=$1
STEP_ID=$2
FILENAME=$3
PAYLOAD=$4

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -X POST "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID/steps/$STEP_ID/documents/$FILENAME" \
  -d "$PAYLOAD"
BASH,
            'get-document' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 3 ]; then
    echo "Usage: get-document <workflow-id> <step-id> <filename>" >&2
    exit 1
fi

WORKFLOW_ID=$1
STEP_ID=$2
FILENAME=$3

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "$AUTH_HEADER" \
  "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID/steps/$STEP_ID/documents/$FILENAME"
BASH,
            'update-document' => <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 4 ]; then
    echo "Usage: update-document <workflow-id> <step-id> <filename> '<json-payload>'" >&2
    exit 1
fi

WORKFLOW_ID=$1
STEP_ID=$2
FILENAME=$3
PAYLOAD=$4

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/_shared.sh"

curl -sS \
  -H "Content-Type: application/json" \
  -H "$AUTH_HEADER" \
  -X PUT "$WORKFLOW_MANAGER_URL/api/wfm/projects/$WORKFLOW_MANAGER_PROJECT_ID/workflows/$WORKFLOW_ID/steps/$STEP_ID/documents/$FILENAME" \
  -d "$PAYLOAD"
BASH,
            default => '#!/usr/bin/env bash'.PHP_EOL,
        };

        return $template.PHP_EOL;
    }
}
