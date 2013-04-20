<?php
  namespace xp\install;

  use \io\Folder;
  use \io\File;
  use \io\collections\FileCollection;
  use \io\collections\iterate\FilteredIOCollectionIterator;
  use \io\collections\iterate\ExtensionEqualsFilter;
  use \io\collections\iterate\NameMatchesFilter;
  use \io\streams\StringReader;
  use \util\cmd\Console;
  use \webservices\json\JsonFactory;
  use \webservices\rest\RestClient;
  use \webservices\rest\RestRequest;
  use \webservices\rest\RestException;

  /**
   * XPI Installer - add modules
   * ===========================
   *
   * Basic usage
   * -----------
   * # This will install the newest release of the specified module
   * $ xpi add vendor/module
   *
   * # This will install a specific version
   * $ xpi add vendor/module 1.0.0
   *
   * Using development versions
   * --------------------------
   * # This will install the master branch of the specified module from GitHub
   * $ xpi add vendor/module :master
   */
  class AddAction extends Action {
    protected static $json;

    static function __static() {
      self::$json= JsonFactory::create();
    }

    /**
     * Execute this action
     *
     * @param  string[] $args command line args
     * @return int exit code
     */
    public function perform($args) {
      $module= Module::valueOf($args[0]);
      $cwd= new Folder('.');
      $base= new Folder($cwd, $module->vendor);

      // Search for module
      $request= create(new RestRequest('/vendors/{vendor}/modules/{module}'))
        ->withSegment('vendor', $module->vendor)
        ->withSegment('module', $module->name)
      ;
      try {
        $info= $this->api->execute($request)->data();
        uksort($info['releases'], function($a, $b) {
          return version_compare($a, $b, '<');
        });
      } catch (RestException $e) {
        Console::$err->writeLine('*** Cannot find module ', $module, ': ', $e->getMessage());
        return 3;
      }

      // Check newest version
      if (!isset($args[1])) {
        if (empty($info['releases'])) {
          Console::$err->writeLine('*** No releases yet for ', $module);
          return 1;
        }
        $version= key($info['releases']);
        $this->cat && $this->cat->info('Using latest release', $version);
      } else if (':' === $args[1]{0}) {
        $version= $args[1];
        $this->cat && $this->cat->info('Using development version', $version);
      } else {
        $version= $args[1];
        if (!isset($info['releases'][$version])) {
          Console::$err->writeLine('*** No such release ', $version, ' for ', $module, ', have ', $info['releases']);
          return 1;
        }

        $this->cat && $this->cat->info('Using version', $version);
      }

      // Determine origin and target
      if (':' === $version{0}) {
        $branch= substr($version, 1);
        $target= new Folder($base, $module->name.'@'.$branch);
        $origin= new GitHubArchive($module->vendor, $module->name, $branch);
      } else {
        $target= new Folder($base, $module->name.'@'.$version);
        $origin= new XarRelease($this->api, $module->vendor, $module->name, $version);
      }

      if ($target->exists()) {
        Console::writeLine($module, ' already exists in ', $target);
      } else {

        // Prepare vendor dir
        if (!$base->exists()) {
          $base->create(0755);
          self::$json->encodeTo(
            array('name' => $base->dirname),
            create(new File($base, 'vendor.json'))->getOutputStream()
          );
        }

        // Fetch
        Console::writeLine($module, ' -> ', $target);
        try {
          $target->create(0755);
          $origin->fetchInto($target);
        } catch (\lang\Throwable $e) {
          Console::writeLine('*** ', $e);
          $target->unlink();
          return 2;
        }
      }

      // Deselect any previously selected version
      foreach (new FilteredIOCollectionIterator(new FileCollection($cwd), new NameMatchesFilter('#^\.'.$module->vendor.'\.'.$module->name.'.*\.pth#')) as $found) {
        $pth= new File($found->getURI());
        Console::writeLine('Deselect ', $pth);
        $pth->unlink();
      }

      // Rebuild paths based on .pth files found in newly selected
      $pth= new File('.'.$module->vendor.'.'.strtr($target->dirname, DIRECTORY_SEPARATOR, '.').'.pth');
      $out= $pth->getOutputStream();
      $base= strtr(substr($target->getURI(), strlen($cwd->getURI())), DIRECTORY_SEPARATOR, '/');
      Console::writeLine('Select ', $pth);
      foreach (new FilteredIOCollectionIterator(new FileCollection($target), new ExtensionEqualsFilter('.pth')) as $found) {
        $r= new StringReader($found->getInputStream());
        while (NULL !== ($line= $r->readLine())) {
          if ('' === $line || '#' === $line{0}) {
            continue;
          } else if ('!' === $line{0}) {
            $out->write('!'.$base.substr($line, 1)."\n");
          } else {
            $out->write($base.$line."\n");
          }
        }
      }
      $out->close();

      Console::writeLine('Done');
      return 0;
    }
  }
?>