# loco: About

## Motivation

The application/platform for which I do most of my daily development has a lot of dependencies and a lot of files --
we're talking about ~120k files (spread out among various PHP/JS/SCSS packages) and 4+ daemons.  And I frequently
switch between OSX/Ubuntu workstations and Debian/Ubuntu servers.  And I work with people on a mix of platforms.

You'd think that `docker` and `docker-compose` would be a good fit.  I've given it 3 or 4 earnest (multi-day) tries in
different years.  Some issues were standard newbie/learning-curve issues, but one seemed persistently unresolvable:
file I/O.  Every workflow/structure I tried had some awkward/slow step.  I thought I was crazy and doing it wrong.  But
step back, and it makes sense: Docker's design revolves around Linux kernel APIs and filesystem magic.  Of course I/O
performance suffers on other platforms.  Duh.  Yes, specific cases can be optimized - but as a general matter,
*expecting* it to be fast isn't reasonable.

So I've moved to `nix` -- which provides a cross-platform package manager.  The ecosystem doesn't feel as mature, but
it gives a lot of the values I wanted from Docker (e.g.  reproducing specific builds; safely mixing versions and
switching versions; avoiding conflicts with the host OS; using manifest-files and/or binaries for distribution).

It does have an issue -- on OSX/Ubuntu, there's no nix-friendly mechanism for starting and stopping services, such as
Redis or MySQL.  (*That gets into NixOS and NixOps -- which, as near as I can tell, would pose the same issue as Docker
on OSX.*) You have to run each command manually... or write some custom launch scripts. I've been using a custom
launch script, but its design started to break-down as I worked on more interesting compositions.

`docker-compose` handles this problem nicely: create a project with its own YAML file; start+stop the project; stitch
together the services with environment-variables.  I just need it to run a little differently...  skip the Docker-Linux
abstractions and use bog-standard local processes instead.  Hence, the "local-compose" (`loco`).

## Critical Comparison

* Strictly speaking, `loco` is a process-manager.  It starts, stops, and restarts processes.  That puts it in the same
  category as sysvinit, runit, or systemd -- but it wasn't conceived for managing a full OS, so it lacks (e.g.) setuid
  support. It is easier to reproduce on additional workstations because it doesn't require any root/sudo/superuser privileges,
  and it generally doesn't rely on the host OS for configuration management. It just runs a couple processes as a regular user.

* Stylistically, it's more like docker-compose -- one creates a per-project YAML dot-file.  The file lists all the services you
  want, and these are glued together with a few environment variables.  Your work is scoped to a specific
  project/folder -- and not the host OS.  To cleanup, just delete the folder.  To try out a variant of the config, just
  make a copy of the folder (or a branch of the git repo).

* Architecturally, it is thinner, less opinionated, and more-limited in value/scope than docker/docker-compose/k8s; which means:

    * It makes no pretense of providing binary-distribution, network/machine management, or enhanced process-isolation.
      It just uses POSIX API's like `exec()` and `getpid()`.

    * All host platforms (including OSX) can achieve native performance.

    * It's easier to inspect the services with off-the-shelf text-editors/IDEs/tools.

    * The configuration options for each service are presented in their canonical forms -- the CLI commands and file-formats
      match the official upstream docs.

* Practically, I'd use it in combination with some other package-distribution channel (`nix run`, `nix-shell`, `docker run`, `apt-get`, `brew`, etc).
  But it's not very opinionated about which you use.

* `loco` is at the proof-of-concept stage. It's not widely used. There are a number of TODOs in [specs.md](specs.md).

* `loco` is more "dev" than "ops".  If you imagine dev-ops as a spectrum, tools like `make` and `npm` live far left on the
  local "development" side; Ansible and `ssh` live far right on the network "operations" side; `loco` lives about 1/3
  from the "dev" side; `docker-compose` lives 1/3 from the "ops" side.  I like this architecture for doing development
  on a multi-tier app.  But it really doesn't care about protecting data, maintaining long-term state,
  defense-in-depth/process-isolation, etc.  If you ask a sysadmin to run production services on it, they might call
  you...  loco.
