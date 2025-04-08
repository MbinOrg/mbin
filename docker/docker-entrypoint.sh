#!/bin/sh
set -e


# Additional Mbin docker configurations

# Use 301 response for image redirects to reduce server load
sed -i 's|redirect_response_code: 302|redirect_response_code: 301|' config/packages/liip_imagine.yaml

# Use S3 file system adapter when S3_KEY env var is not empty
if [ -n "$S3_KEY" ]; then
	sed -i 's|adapter: default_adapter|adapter: kbin.s3_adapter|' config/packages/oneup_flysystem.yaml
fi

# Needed to apply the above config changes
php bin/console cache:clear


if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

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

		if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	# Solution to allow non-root users, given here: https://github.com/dunglas/symfony-docker/issues/679#issuecomment-2501369223
	chown -R $MBIN_USER var /data /config

	echo 'PHP app ready!'
fi

exec /usr/sbin/gosu $MBIN_USER "$@"
