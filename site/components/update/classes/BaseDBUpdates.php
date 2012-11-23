<?php namespace components\update\classes; if(!defined('TX')) die('No direct access.');

abstract class BaseDBUpdates
{
  
  /*
  
  Static properties:
  - core_package_data
  - component_package_data
  - template_package_data
  - theme_package_data
  - queued_operations
  
  Properties:
  ~ component
  ~ is_core
  ~ template
  ~ theme
  ~ updates
  - package
  
  Static methods:
    base_dir
    clear_global_cache
    init_statics
    package_data
    process_queue
  
  Methods:
  + clear_cache
  + __construct
  + current version
  + install
  + latest version
  + uninstall
  + update
  ~ backup
  ~ get_base dir
  ~ get_package data
  ~ package
  ~ next version
  ~ queue
  - version bump
  
  */
  
  static private
    $core_package_data,
    $component_package_data,
    $template_package_data,
    $theme_package_data,
    $queued_operations;
  
  protected
    $component,
    $is_core,
    $template,
    $theme,
    $updates;
  
  private
    $package;
  
  /* ---------- Static ---------- */
  
  static function base_dir($type = 'core', $name = null){
    
    switch($type){
      
      case 'core':
        return PATH_BASE;
      
      case 'component':
        return PATH_COMPONENTS.DS.$name;
      
      case 'template':
        return PATH_TEMPLATES.DS.'custom'.DS.$name;
      
      case 'theme':
        return PATH_THEMES.DS.'custom'.DS.$name;
      
      default:
        return false;
      
    }
    
  }
  
  static function clear_global_cache()
  {
    
    self::$core_package_data = null;
    self::$component_package_data = null;
    self::$template_package_data = null;
    self::$theme_package_data = null;
    
  }
  
  static function init_statics()
  {
    
    //Use an array unfortunately, because Data() does not support storing closures.
    self::$queued_operations = array();
    
  }
  
  static function package_data($type = 'core', $name = null)
  {
    
    switch($type){
      
      case 'core':
        if(isset(self::$core_package_data))
          return self::$core_package_data;
        break;
      
      case 'component':
        if(isset(self::$component_package_data) && self::$component_package_data->{$name}->is_set())
          return self::$component_package_data->{$name};
        break;
      
      case 'template':
        if(isset(self::$template_package_data) && self::$template_package_data->{$name}->is_set())
          return self::$template_package_data->{$name};
        break;
      
      case 'theme':
        if(isset(self::$theme_package_data) && self::$theme_package_data->{$name}->is_set())
          return self::$theme_package_data->{$name};
        break;
      
      default:
        throw new \exception\Exception('Uh oh, '.$type);
        return false;
      
    }
    
    //Where the package at?
    $packageFile = self::base_dir($type, $name).DS.'.package'.DS.'package.json';
    
    //Make sure the package file is there.
    if(!is_file($packageFile))
      throw new \exception\FileMissing('Package folder does not contain package.json file');
    
    //Get package data.
    $data = Data(json_decode(file_get_contents($packageFile), true));
    
    //Cache the data.
    switch($type){
      
      case 'core':
        self::$core_package_data = $data;
        break;
      
      case 'component':
        if(!isset(self::$component_package_data))
          self::$component_package_data = Data();
        self::$component_package_data->{$name}->become($data);
        break;
      
      case 'template':
        if(!isset(self::$template_package_data))
          self::$template_package_data = Data();
        self::$template_package_data->{$name}->become($data);
        break;
      
      case 'theme':
        if(!isset(self::$theme_package_data))
          self::$theme_package_data = Data();
        self::$theme_package_data->{$name}->become($data);
        break;
      
    }
    
    return $data;
    
  }
  
  static function process_queue()
  {
    
    //It might be we never queued anything.
    if(!isset(self::$queued_operations))
      return;
    
    //Go over each queue element.
    foreach(self::$queued_operations as $operation_data)
    {
      
      //Find out what this component is.
      $package = self::package_data('component', $operation_data['component']);
      $package_db = tx('Sql')
        ->table('update', 'Packages')
        ->where('title', "'{$package->title}'")
        ->execute_single()
        
        //In case it's not found.
        ->is('empty', function(){
          throw new \exception\NotFound('The package for this component could not be found in the database');
        });
      
      //Get the version we're talking about.
      $version = tx('Sql')
        ->table('update', 'PackageVersions')
        ->where('package_id', $package_db->id)
        ->where('version', "'{$operation_data['min_version']}'")
        ->limit(1)
        ->execute_single()
        
        //In case it's not found.
        ->is('empty', function(){
          throw new \exception\NotFound('The given min_version was not found, it needs to be listed in the package.json of it\'s component');
        });
      
      //Find out if this or a later version has been installed.
      $current = strtotime($package_db->installed_version_date->get('string'));
      $min = strtotime($version->date->get('string'));
      
      //Uh oh!
      if($current < $min)
        throw new \exception\Expected('The installed version is lower than the minimum version for this operation');
      
      //Otherwise, execute the operation.
      $operation_data['operation']($package_db->installed_version->get());
      
    }
    
  }
  
  /* ---------- Public ---------- */
  
  public function clear_cache()
  {
    
    $this->package = null;
    self::clear_global_cache();
    
  }
  
  public function __construct()
  {
    
    //Check we set a base_dir.
    $this->get_base_dir();
    
    //We are lazy and prefer to set an array on the implementation classes.
    $this->updates = Data($this->updates);
    
  }
  
  public function current_version()
  {
    
    return $this->package()->installed_version->get();
    
  }
  
  public function install($dummydata=false, $forced=false, $update_to_latest=true)
  {
    
    $that = $this;
    raw($dummydata, $forced, $update_to_latest);
    
    if(!$this->package()->installed_version->is_empty() && $forced !== true)
      throw new \exception\Exception('Package '.$this->package()->title.' has already been installed and $forced is false');
    
    //Save the latest version.
    $latest = Data();
    
    //Go over each version.
    $this->get_package_data()->versions->each(function($version)use(&$latest, $that){
      
      //If this is a later version.
      $version->timestamp->set(strtotime($version->date->get()));
      if($version->timestamp->get() > $latest->timestamp->get())
      {
        
        if(method_exists($that, 'install_'.str_replace('.', '_', $version->version->get())))
          $latest = $version;
        
      }
      
    });
    
    if($latest->is_empty()) throw new \exception\Exception('No install method exists for package '.$this->package()->title);
    
    call_user_func_array(array($this, 'install_'.str_replace('.', '_', $latest->version->get())), array($dummydata, $forced));
    $this->version_bump($latest->version);
    
    if($update_to_latest === true) $this->update($forced);
    return true;
    
  }
  
  public function latest_version()
  {
    
    //Save the latest version.
    $latest = Data();
    
    //Go over each version.
    $this->get_package_data()->versions->each(function($version)use(&$latest){
      
      //Bump latest version if needed.
      $version->timestamp->set(strtotime($version->date->get()));
      if($version->timestamp->get() > $latest->timestamp->get())
        $latest = $version;
      
    });
    
    return $latest->version->get();
    
  }
  
  public function uninstall($forced=false)
  {
    
    throw new \exception\Exception('Not implementated yet');
    
  }
  
  public function update($forced=false, $maybe_install=false)
  {
    
    raw($forced, $maybe_install);
    
    //If we're at the current version, there's nothing to do.
    if($this->current_version() === $this->latest_version()) return false;
    
    //If there's no current_version set, we need to install from scratch. If the parameters allow it.
    if($this->current_version() == '')
    {
      
      if($maybe_install === true){
        $this->install(false, $forced, true);
        return true;
      }
      
      else{
        throw new \exception\Exception('Update was called with $maybe_install set to false and no version was previously installed');
      }
      
    }
    
    //What is the update method for this?
    $next = $this->next_version();
    
    if(!$next){
      tx('Logging')->log('Update', 'Updating DB', 'Dead end in update path for package '.$this->package()->title.' at version '.$this->current_version());
      return false;
    }
    
    $method = 'update_to_'.str_replace('.', '_', $next);
    
    //If it exists, call it.
    if(method_exists($this, $method))
    {
      
      //Log and perform the update call.
      tx('Logging')->log('Update', 'Updating DB', 'Calling '.$method.' for package '.$this->package()->title.' from version '.$this->current_version());
      call_user_func_array(array($this, $method), array($this->current_version(), $forced));
      $this->version_bump($next);
      
      //Repeat.
      $this->update($forced, $maybe_install);
      return true;
      
    }
    
    //Otherwise just report we're skipping it, but once looked for it.
    else{
      tx('Logging')->log('Update', 'Updating DB', 'No method '.$method.' for package '.$this->package()->title.' from version '.$this->current_version());
      $this->version_bump($next);
      return true;
    }
    
  }
  
  /* ---------- Protected ---------- */
  
  protected function get_base_dir(){
    
    if($this->is_core === true)
      return self::base_dir('core');
    
    elseif(isset($this->component))
      return self::base_dir('component', $this->component);
    
    elseif(isset($this->template))
      return self::base_dir('template', $this->template);
    
    elseif(isset($this->theme))
      return self::base_dir('theme', $this->theme);
    
    throw new \exception\Programmer('Type has not been defined for the DBUpdates instance, use protected attributes $is_core, $component, $template or $theme for this');
    
  }
  
  protected function get_package_data()
  {
    
    if($this->is_core === true)
      return self::package_data('core');
    
    elseif(isset($this->component))
      return self::package_data('component', $this->component);
    
    elseif(isset($this->template))
      return self::package_data('template', $this->template);
    
    elseif(isset($this->theme))
      return self::package_data('theme', $this->theme);
    
    throw new \exception\Programmer('Type has not been defined for the DBUpdates instance, use protected attributes $is_core, $component, $template or $theme for this');
    
  }
  
  protected function package()
  {
    
    //Do some caching.
    if($this->package) return $this->package;
    
    //We can only do manual types.
    if($this->get_package_data()->type->get() !== 'manual')
      throw new \exception\Exception('Package type other than manual has not been implemented yet.');
    
    //Reference this instance.
    $that = $this;
    
    //Get the package from the database.
    //Use a try catch in case we're installing the update package and the tables don't exist.
    try{
      $package = tx('Sql')
        ->table('update', 'Packages')
        ->where('title', "'".$this->get_package_data()->title."'")
        ->execute_single();
    }
    
    //In case of a Sql exception we are self-installing.
    //Return an empty data object.
    catch(\exception\Sql $ex){
      return Data();
    }
    
    //Don't cache and return a new model if the package was not in the database.
    if($package->is_empty()){
      return tx('Sql')->model('update', 'Packages')->set(array(
        'title' => $this->get_package_data()->title,
        'description' => $this->get_package_data()->description,
        'type' => 0
      ));
    }
    
    $this->package = $package;
    return $package;
    
  }
  
  protected function next_version($version=null)
  {
    
    //Raw data.
    raw($version);
    
    //If none is given, get the current version.
    if(!$version) $version = $this->current_version();
    
    //Get the next version.
    $version = $this->updates->{$version};
    if($version->is_set())
      return $version->get();
    return false;
    
  }
  
  protected function queue($data, \Closure $operation)
  {
    
    //Note: this function does not queue for core versions, only components.
    //This is because the core updates are always executed first so no queue is needed from components to the core.
    //And the core is still able to queue for components, should that for some awkward reason ever be needed.
    
    //Validate input.
    $data = Data($data)->having('component', 'min_version')
      ->component->validate('Component', array('required', 'string', 'not_empty'))->back()
      ->min_version->validate('Minimum version', array('required', 'string', 'not_empty'))->back()
    ;
    
    //See if the component is availabe.
    if(!tx('Component')->available($data->component))
      throw new \exception\NotFound('The component '.$data->component.' is not available.');
    
    //Since the min_version we designated might still be in the line-up to be installed,
    // wait untill later to check this is a valid version or whether it will be installed at all.
    array_push(self::$queued_operations, array(
      'component' => $data->component->get(),
      'min_version' => $data->min_version->get(),
      'operation' => $operation
    ));
    
  }
  
  private function version_bump($version)
  {
    
    raw($version);
    
    //In case of a self-install the package will not be inserted yet.
    if(!$this->package()->id->is_set() && $this->component === 'update'){
      
      //Get the version data from the package.json.
      $version = $this->get_package_data()->versions->filter(function($packageVersion)use($version){
        return $packageVersion->version->get('string') === $version;
      })->{0};
      
      //Insert it into the database.
      $dbPackage = $this->package()->merge(array(
        'installed_version' => $version->version,
        'installed_version_date' => $version->date
      ))->save();
      
      //Make sure the rest of the version data is also inserted.
      $this->get_package_data()->versions->each(function($version)use($dbPackage){
        
        //Try find this version.
        $dbVersion = tx('Sql')
          ->table('update', 'PackageVersions')
          ->where('package_id', $dbPackage->id)
          ->where('version', "'{$version->version}'")
          ->execute_single()
          
          //If it doesn't exist, create it now.
          ->is('empty', function()use($version, $dbPackage){
            
            $dbVersion = tx('Sql')
              ->model('update', 'PackageVersions')
              ->set($version->having('version', 'date', 'description'))
              ->package_id->set($dbPackage->id)->back()
              ->save();
            
            //Insert the changes of this version.
            $version->changes->each(function($change)use($dbVersion){
              
              tx('Sql')
                ->model('update', 'PackageVersionChanges')
                ->set($change->having('title', 'description', 'url'))
                ->url->is('empty', function($url){ $url->set('NULL'); })->back()
                ->package_version_id->set($dbVersion->id)->back()
                ->save();
              
            });
            
          })//end - is empty
        ;
        
      });
      
    }
    
    //Normal version bump.
    else{
      $version = tx('Sql')
        ->table('update', 'PackageVersions')
        ->where('package_id', $this->package()->id)
        ->where('version', "'{$version}'")
        ->execute_single()
        ->is('empty', function()use($version){
          throw new \exception\NotFound('Version '.$version.' is not defined for package '.$this->package()->title);
        });
      
      //Do the bump.
      $this->package()->merge(array(
        'installed_version' => $version->version,
        'installed_version_date' => $version->date
      ))->save();
    }
    
  }
  
}

//Initialize the static things.
BaseDBUpdates::init_statics();
