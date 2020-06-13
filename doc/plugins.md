# Plugins

Loco plugins are PHP files which register event listeners.

## Example: Update environtment

```php
// FILE: /etc/loco/plugin/locale.php
use Loco\Loco;
Loco::dispatcher()->addListener('loco.system.env', function($e){
  $e->system->environment->set('LOCALE_ARCHIVE', '/usr/lib/locale/locale-archive');
});
```

## Example: Add command

```php
// FILE: /etc/loco/plugin/jump-command.php
use Loco\Loco;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

Loco::dispatcher()->addListener('loco.app.commands', function($e){
  $e['commands'][] = new class extends \Symfony\Component\Console\Command\Command {
    protected function configure() {
      $this->setName('jump');
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
      $output->writeln('Jump!');
    }
  };
});
```

## Loading

The environment variable `LOCO_PLUGIN_PATH` specifies a list of folders to
search for plugins. All plugins in the path will be activated immediately
during startup.

The default value of `LOCO_PLUGIN_PATH` is:

```bash
LOCO_PLUGIN_PATH=/etc/loco/plugin:/usr/share/loco/plugin:/usr/local/share/loco/plugin:$HOME/.config/loco/plugin
```

Once the `loco.yml` file is loaded, it may specify additional plugins.
(However, these will not have access to early bootstrap events.)

## Events

* `loco.app.boot`
* `loco.app.run`
* `loco.app.commands`
