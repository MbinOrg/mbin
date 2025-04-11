#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Install dependencies if missing (needed for development)
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	# Additional Mbin docker configurations (only for production)
	if [ "$APP_ENV" = 'prod' ]; then
		# Use 301 response for image redirects to reduce server load
		sed -i 's|redirect_response_code: 302|redirect_response_code: 301|' config/packages/liip_imagine.yaml

		# Override log level when PHP_LOG_LEVEL is not empty
		if [ -n "$PHP_LOG_LEVEL" ]; then
			sed -i "s|action_level: error|action_level: $PHP_LOG_LEVEL|" config/packages/monolog.yaml
		fi

		# Use S3 file system adapter when S3_KEY is not empty
		if [ -n "$S3_KEY" ]; then
			sed -i 's|adapter: default_adapter|adapter: kbin.s3_adapter|' config/packages/oneup_flysystem.yaml
		fi
	fi

	# Needed to apply the above config changes
	php bin/console cache:clear

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
	fi

	# Solution to allow non-root users, given here: https://github.com/dunglas/symfony-docker/issues/679#issuecomment-2501369223
	chown -R $MBIN_USER var /data /config

	echo 'PHP app ready!'
fi

exec /usr/sbin/gosu $MBIN_USER "$@"
