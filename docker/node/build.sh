#/bin/sh
set -e

if [ -d node_modules ] || [ "$FORCE" != "y" ] ; then
  echo "Not rebuilding. Pass env var FORCE=y to rebuild anyway"
  exit
fi

npm ci --include=dev
npm run build
