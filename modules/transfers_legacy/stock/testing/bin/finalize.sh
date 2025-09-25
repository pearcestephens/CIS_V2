#!/usr/bin/env sh
# SSH helper: Finalize pack
DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php "$DIR/../cli_test_runner.php" --action=finalize "$@"