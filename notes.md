- [ ] add link to [dev server docs](docs/04-contributing/development_server.md) in `CONTRIBUTING.md`
- [ ] mention how to activate debug logs for symfony (`bin/console -vvv some-command`)
# Docker dev

- [ ] Make a separate `compose.dev.yml`
  - [ ] Doc: `ln -s compose.dev.yml compose.override.yml`
  - [ ] rewrite `Dockerfile` to  have targets for dev
  - [ ] Use `Dockerfile` targets in `compose.yml` for dev and prod
  - [ ] `host.docker.internal` doesn't exist on linux because it's not running docker in a VM
    - [ ] conditionally add following for linux\
    ```yaml
    extra_hosts:
      - "host.docker.internal:host-gateway"
    ```
  - [ ] Make a separate `.env.dev` for development
  - [ ] use `.env.dev` in `compose.dev.yml`

- [ ] mention that resetting means `sudo rm -rf docker/storage`
  - [ ] move `.gitignore` out of storage otherwise resetting still creates a commit by deleting `.gitignore`
- [ ] `docker/storage` needs to be writable to `mbin` user!
  - [ ] `sudo chmod 777 docker/storage/*`
- [ ] `MESSENGER_TRANSPORT_DSN=doctrine://default` doesn't work

- [ ] nixos needs [iptables rules](https://discourse.nixos.org/t/docker-container-not-resolving-to-host/30259/8) for xdebug to access host
