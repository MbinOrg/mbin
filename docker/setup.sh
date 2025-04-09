#!/usr/bin/env bash

# Ensure script is always ran in Mbin's root directory.
cd "$(dirname "$0")/.."

if [[ "$1" == "" || "$1" == "-h" || "$1" == "--help" ]]; then
    cat << EOF
Usage: ./docker/setup.sh MODE DOMAIN
Automate your Mbin docker setup!

MODE needs to be either "prod" or "dev".
DOMAIN will set the correct domain related fields in the .env file. Use "localhost" if you are just testing locally.

Examples:
  ./docker/setup.sh prod mbin.domain.tld
  ./docker/setup.sh dev localhost
EOF
  exit 0
fi

case $1 in
    prod)
        mode=prod
        ;;
    dev)
        mode=dev
        ;;
    *)
        echo "Invalid mode provided: $1"
        echo "Must be either prod (recommended for most cases) or dev."
        exit 1
        ;;
esac

domain=$2
if [[ -z $domain ]]; then
  echo "DOMAIN must be provided. Use "localhost" if you are just testing locally."
  exit 1
fi

verify_no_file () {
  if [ -f "$1" ]; then
    echo "ERROR: $1 file already exists. Cannot continue setup."
    exit 1
  fi
}
verify_no_dir () {
  if [ -d "$1" ]; then
    echo "ERROR: $1 directory already exists. Cannot continue setup."
    exit 1
  fi
}

verify_no_file .env
verify_no_file compose.override.yaml
verify_no_dir storage

echo "Starting Mbin $mode setup..."
echo

echo "Generating .env file with passwords..."
GEN_PASSWORD_LENGTH=32
GEN_PASSWORD_REGEX='!Change\w*!'
while IFS= read -r line; do
  # Replace instances of !ChangeAnything! with a generated password.
  if [[ $line =~ $GEN_PASSWORD_REGEX ]]; then
    PASSWORD=$(tr -dc A-Za-z0-9 < /dev/urandom | head -c $GEN_PASSWORD_LENGTH)
    # Save oauth password for later
    if [[ ${BASH_REMATCH[0]} == '!ChangeThisOauthPass!' ]]; then
      OAUTH_PASS=$PASSWORD
    fi
    line=${line/${BASH_REMATCH[0]}/$PASSWORD}
  fi

  # Replace "mbin.domain.tld" with passed in domain
  if [[ -n $domain ]]; then
    line=${line/mbin.domain.tld/$domain}
  fi

  # Populate MBIN_USER field
  if [[ $line == 'MBIN_USER=1000:1000' ]]; then
    line="MBIN_USER=$(id -u):$(id -g)"
  fi

  # Populate OAUTH_ENCRYPTION_KEY field
  if [[ $line == 'OAUTH_ENCRYPTION_KEY=' ]]; then
    line="$line$(openssl rand -hex 16)"
  fi

  echo "$line" >> .env
done < .env.example_docker

echo "Creating compose.override.yaml file... Any additional customizations to the compose setup should be added here."
if [[ $mode == "dev" ]]; then
   cat > compose.override.yaml << EOF
include:
  - compose.dev.yaml
EOF
else
   cat > compose.override.yaml << EOF
# Customizations to the docker compose should be added here.
EOF
fi

echo "Setting up storage directories..."
mkdir -p storage/{caddy_config,caddy_data,media,messenger_logs,oauth,php_logs,postgres,rabbitmq_data,rabbitmq_logs}
echo "Configuring OAuth2 keys..."
openssl genrsa -des3 -out ./storage/oauth/private.pem -passout "pass:$OAUTH_PASS" 4096
openssl rsa -in ./storage/oauth/private.pem --outform PEM -pubout -out ./storage/oauth/public.pem -passin "pass:$OAUTH_PASS"

echo
echo "Mbin environment setup complete!"
echo "Please refer back to the documentation for finishing touches."
