- [ ] add link to [dev server docs](docs/04-contributing/development_server.md) in `CONTRIBUTING.md`
- [ ] mention how to activate debug logs for symfony (`bin/console -vvv some-command`)
# Docker dev

- [ ] Use whitelist approach in .dockerignore instead of blacklist
- [x] Make a separate `compose.dev.yml`
  - [ ] Doc: `ln -s compose.dev.yml compose.override.yml`
  - [x] rewrite `Dockerfile` to  have targets for dev
  - [x] Use `Dockerfile` targets in `compose.yml` for dev and prod
  - [ ] `host.docker.internal` doesn't exist on linux because it's not running docker in a VM
    - [x] conditionally add following for linux\
    ```yaml
    extra_hosts:
      - "host.docker.internal:host-gateway"
    ```
  - [x] Make a separate `.env.dev` for development
  - [x] use `.env.dev` in `compose.dev.yml`
- [x] Update `compose.prod.yml`
  - [x] Use separate `entrypoint.sh` for prod image? **NOPE**
- [ ] mention that resetting means `sudo rm -rf docker/storage`
  - [ ] move `.gitignore` out of storage otherwise resetting still creates a commit by deleting `.gitignore`
- [x] `docker/storage` needs to be writable to `mbin` user!
  - [x] `sudo chmod 777 docker/storage/*`
- [ ] `MESSENGER_TRANSPORT_DSN=doctrine://default` doesn't work

- [ ] Doc: inform how to enable xdebug (enabling it by default slows everything down)

- [ ] nixos needs [iptables rules](https://discourse.nixos.org/t/docker-container-not-resolving-to-host/30259/8) for xdebug to access host

# Prod

Production now allows running a separate reverse proxy than caddy.
This is achieved by copying the necessary files into a common volume at startup.
`php` copies to `commonVolume` which can be accessed by `reverseProxy`
