#!/usr/bin/env bash

# Ensure script is always ran in Mbin's root directory.
cd "$(dirname "$0")/.."

if [[ "$1" == "" || "$1" == "-h" || "$1" == "--help" ]]; then
    cat << EOF
Usage: ./docker/setup.sh MODE [DOMAIN]
Automate your Mbin docker setup!

MODE needs to be either "prod" or "dev".
DOMAIN can optionally be specified, and will set the correct domain related fields in the .env file.

Examples:
  ./docker/setup.sh prod mbin.domain.tld
  ./docker/setup.sh dev
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
        echo "Must be either prod (default) or dev."
        exit 1
        ;;
esac

domain=$2

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
verify_no_file compose.override.yml
verify_no_dir docker/storage
verify_no_file config/oauth2/private.pem
verify_no_file config/oauth2/public.pem

echo Starting Mbin $mode setup...
echo

echo Generating .env file with passwords...
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

  # Populate OAUTH_ENCRYPTION_KEY field
  if [[ $line == 'OAUTH_ENCRYPTION_KEY=' ]]; then
    line="$line$(openssl rand -hex 16)"
  fi

  echo "$line" >> .env
done < .env.example_docker_$mode

echo Copying compose.override.yml file...
cp compose.$mode.yml compose.override.yml

echo Setting up storage directories...
mkdir -p docker/storage/media docker/storage/caddy_config docker/storage/caddy_data docker/storage/logs
chown $USER:$USER -R docker/storage

echo Configuring OAuth2 keys...
openssl genrsa -des3 -out ./config/oauth2/private.pem -passout "pass:$OAUTH_PASS" 4096
openssl rsa -in ./config/oauth2/private.pem --outform PEM -pubout -out ./config/oauth2/public.pem -passin "pass:$OAUTH_PASS"

echo
echo Mbin setup complete!
if [[ -z $domain ]]; then
  echo Don\'t forget to update SERVER_NAME, KBIN_DOMAIN, and KBIN_STORAGE_URL fields in your .env file.
fi
echo Setting up SMTP and Captcha is also recommended.
