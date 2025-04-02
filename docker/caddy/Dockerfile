FROM caddy:2.9.1-builder-alpine AS builder

# Build Caddy with the Mercure and Vulcain and brotil cache modules
RUN xcaddy build \
	--with github.com/dunglas/mercure/caddy \
	--with github.com/dunglas/vulcain/caddy \
	--with github.com/ueffel/caddy-brotli


FROM caddy:2.9.1 as final

COPY --link docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=builder /usr/bin/caddy /usr/bin/caddy

# Expose port 2019 for caddy metric
EXPOSE 2019/tcp
