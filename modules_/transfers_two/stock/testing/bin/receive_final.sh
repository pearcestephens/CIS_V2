#!/usr/bin/env sh
# SSH helper: Receive final
DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php "$DIR/../cli_test_runner.php" --action=receive_final "$@"