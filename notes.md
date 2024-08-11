- [ ] add link to [dev server docs](docs/04-contributing/development_server.md) in `CONTRIBUTING.md`
- [ ] mention how to activate debug logs for symfony (`bin/console -vvv some-command`)
# Docker dev

- [ ] Make a separate `compose.dev.yml`
- [ ] Make a separate `.env.dev` for development
- [ ] Indicate that `.env.dev` should be copied into [/docker/](/docker/)
- [ ] mention that resetting means `sudo rm -rf docker/storage`
  - [ ] move `.gitignore` out of storage otherwise resetting still creates a commit by deleting `.gitignore`
- [ ] `docker/storage` needs to be writable to `mbin` user!
  - [ ] `sudo chmod 777 docker/storage/*`
- [ ] `MESSENGER_TRANSPORT_DSN=doctrine://default` doesn't work
