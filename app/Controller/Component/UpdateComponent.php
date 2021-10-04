<?php
if (file_exists(ROOT . DS . 'lib' . DS . 'Cake' . DS . 'Core' . DS . 'CakeObject.php')) {
    App::uses('CakeObject', 'Core');
} else {
    class CakeObject extends Object
    {
    }
}

class UpdateComponent extends CakeObject
{
    public $components = ['Session', 'Configuration', 'Lang'];

    public $cmsVersion;
    public $lastVersion;
    public $errorUpdate;
    private $updateLogFile;
    private $updateLogFileName;
    private $bypassFiles = [
        '.DS_Store',
        '.htaccess',
        'empty',
        'app/Config/database.php',
        'config/secure',
        '__MACOSX',
        'config.json',
        'theme.default.json'
    ];
    private $source = [
        'repo' => 'MineWebCMS',
        'owner' => 'MineWeb',
        'versionFile' => 'VERSION'
    ];
    private $controller;

    public function shutdown($controller)
    {
    }

    public function beforeRender($controller)
    {
    }

    public function beforeRedirect()
    {
    }

    public function startup($controller)
    {
    }

    /**
     * Used to set update component into controller and check updates
     */
    public function initialize($controller)
    {
        $this->controller = $controller;
        $this->Lang = $this->controller->Lang;
        $controller->set('Update', $this);

        $this->updateLogFile = ROOT . DS . 'app' . DS . 'tmp' . DS . 'logs' . DS . 'update' . DS;
        $this->updateCacheFile = ROOT . DS . 'config' . DS . 'update';
        $this->errorUpdate = $this->Lang->get('UPDATE__FAILED');

        // Check if an update is available
        $this->check();
    }

    /**
     * Used to check via cache or Github if a new version is available
     */
    private function check()
    {
        $cmsVersion = file_get_contents(ROOT . DS . $this->source['versionFile']);
        $this->cmsVersion = trim($cmsVersion);

        if (!file_exists($this->updateCacheFile) || strtotime('+5 hours', filemtime(ROOT . DS . 'config' . DS . 'update')) < time()) {
            $remoteVersion = $this->getLatestRelease();
            if ($remoteVersion) file_put_contents($this->updateCacheFile, $remoteVersion);
        }
        $this->lastVersion = trim(isset($remoteVersion) ? $remoteVersion : file_get_contents($this->updateCacheFile));
        if (!$this->lastVersion) $this->lastVersion = $this->cmsVersion;
    }

    /**
     * Used to retrieve last release
     */
    private function getLatestRelease()
    {
        try {
            $release = json_decode($this->controller->sendGetRequest("https://api.github.com/repos/{$this->source['owner']}/{$this->source['repo']}/releases/latest"));
        } catch (Exception $e) {
            $this->log('Got an error on get latest release:', $e);
            return null;
        }
        return substr($release->name, 1); // We need to remove `v` from `v1.6.2`
    }

    /**
     * Return HTML content if an update is available
     */
    public function available()
    {
        if (version_compare($this->cmsVersion, $this->lastVersion, '<')) {
            return "<div class='alert alert-secondary'>" .
                "{$this->Lang->get('UPDATE__AVAILABLE_TYPE_CMS')} {$this->Lang->get('UPDATE__AVAILABLE')} {$this->Lang->get('UPDATE__CMS_VERSION')} : " .
                "{$this->cmsVersion}, {$this->Lang->get('UPDATE__LAST_VERSION')} : {$this->lastVersion} " .
                "<a href='" . Router::url(['controller' => 'update', 'action' => 'index', 'admin' => true]) . "' style='margin-top: -6px;' class='btn float-right'>" .
                $this->Lang->get('GLOBAL__UPDATE') .
                "</a>" .
                "</div>";
        }
    }

    /**
     * Update CMS files
     */
    public function updateCMS($componentUpdated = false)
    {
        set_time_limit(0);
        if (!$componentUpdated) {
            // Here, this is the first step of the update. We're trying to keep this component
            // updated for update (in case of we need new behavior).
            // So we download, unzip the new version and copy the component, then, restart the update
            if (!$this->downloadUpdate()) return false;

            $zip = new ZipArchive;
            if ($zip->open(ROOT . DS . 'app' . DS . 'tmp' . DS . $this->lastVersion . '.zip') !== true) return false;

            $path = DS . 'app' . DS . 'Controller' . DS . 'Component' . DS . 'UpdateComponent.php';
            $newContent = $zip->getFromName("{$this->source['repo']}-{$this->lastVersion}{$path}");
            file_put_contents(ROOT . $path, $newContent);
            $zip->close();
            return true;
        }

        // We need to copy all files
        // We avoid extractTo() method because we need to avoid copying $bypassFiles
        $zip = new ZipArchive;
        if ($zip->open(ROOT . DS . 'app' . DS . 'tmp' . DS . $this->lastVersion . '.zip') !== true) return false;
        $updateFile = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            // Get some infos on file
            $filename = $zip->getNameIndex($i);
            $stats = $zip->statIndex($i);
            $fileinfo = pathinfo($filename);
            // We remove github root folder from name
            $filename = substr($filename, strlen("{$this->source['repo']}-{$this->lastVersion}/"));
            $dirname = substr($fileinfo['dirname'], strlen("{$this->source['repo']}-{$this->lastVersion}/"));
            // We check if that file need to be updated or not
            if (in_array($filename, $this->bypassFiles)) continue;
            // If the folder doesn't exist, create it recursively
            if (!is_dir(ROOT . DS . $dirname)) mkdir(ROOT . DS . $dirname, 0775, true);

            // Copy file content if it's a file
            if ($stats['size'] > 0) {
                // We stop here if the file isn't writable
                $path = "zip://" . ROOT . DS . "app" . DS . "tmp" . DS . $this->lastVersion . ".zip#{$this->source['repo']}-{$this->lastVersion}/" . "$filename";
                $updateFile[$path] = ROOT . DS . $filename;
                $file = $updateFile[$path];
                if (file_exists($file) && !is_writable($file)) {
                    $this->errorUpdate = $this->Lang->get('UPDATE__FAILED_FILE', [
                        '{FILE}' => $file,
                    ]);
                    $this->log("The file " . $file . " is not writable!");
                    return false;
                }
            }
        }

        $this->log("Start UPDATE");

        // We copy the files here
        foreach ($updateFile as $key => $v) {
            $has_key = hash_file('sha1', $key);
            $hash = hash_file('sha1', $v);
            if($has_key == $hash)
                continue;
            if (!copy($key, $v)) {
                $this->errorUpdate = $this->Lang->get('UPDATE__FAILED_FILE', [
                    '{FILE}' => $v,
                ]);
                $this->log("Failed to copy file from $key to " . $v);
                return false;
            }
            $this->log("The file " .$v. " was replaced with success !");
        }

        $this->log("End UPDATE");

        $zip->close();

        // Remove zip
        @unlink(ROOT . DS . 'app' . DS . 'tmp' . DS . $this->lastVersion . '.zip');

        // Clear cache
        Cache::clearGroup(false, '_cake_core_');
        Cache::clearGroup(false, '_cake_model_');

        // Update database
        return $this->updateDb();
    }

    /**
     * Used to download update from Github
     */
    private function downloadUpdate()
    {
        // We download the release we need
        if (!($filesContent = $this->controller->sendGetRequest("https://github.com/{$this->source['owner']}/{$this->source['repo']}/archive/v{$this->lastVersion}.zip")))
            return false;
        $write = fopen(ROOT . DS . 'app' . DS . 'tmp' . DS . $this->lastVersion . '.zip', 'w+');
        if (!fwrite($write, $filesContent)) {
            $this->log('[Update] Save files failed.');
            return false;
        }
        fclose($write);
        return true;
    }

    /**
     * Used to update database schema.
     * This read new app/Config/Schema/schema.php and compare it with a generated
     * one from database.
     */

    public function updateDb()
    {
        // Load updated schema
        App::uses('CakeSchema', 'Model');

        $options = [
            'name' => 'AppUpdate',
            'path' => ROOT . DS . 'app' . DS . 'Config' . DS . 'Schema',
            'file' => 'schemaUpdate.php',
            'plugin' => null,
            'connection' => 'default',
            'models' => false
        ];

        // Here we need to copy the new schema file to be able to require it
        // Indeed, the old schema file has already been loaded (in plugin validation)
        // and we can't load a file twice (we'll have some conflicts about re-defining
        // the class, so we need to update the class name too)
        $get_new_file = file_get_contents($options['path'] . DS . 'schema.php');
        $replace_class_name = str_replace('AppSchema', 'AppUpdateSchema', $get_new_file);
        file_put_contents($options['path'] . DS . $options['file'], $replace_class_name);

        $schema = new CakeSchema($options);

        $newSchema = $schema->load($options); // This is a new instance of CakeSchema from schema.php loaded
        // Generate a schema from database
        $currentSchema = $schema->read($options); // This is the current CakeShema instance


        // Compare them
        $diffSchema = $schema->compare($currentSchema, $newSchema); // This is an object of diff between schemas

        unlink($options['path'] . DS . $options['file']);

        $db = ConnectionManager::getDataSource('default');
        $queries = [];
        foreach ($diffSchema as $table => $changes) {

            // If we have columns to drop, we need to check this is not about a plugin
            if (isset($changes['drop'])) {
                foreach ($changes['drop'] as $column => $structure) { // For each drop, check column name
                    if (count(explode('-', $column)) > 1) { // Plugin columns are prefixed by `pluginname-<column>`
                        unset($diffSchema[$table]['drop'][$column]);
                    }
                }
            }
            // Just delete `drop` action if we have removed all columns to drop (above)
            if (isset($changes['drop']) && count($diffSchema[$table]['drop']) <= 0) {
                unset($diffSchema[$table]['drop']);
            }

            // If we have actions (maybe we've removed the only action `drop`)
            if (count($diffSchema[$table]) > 0) {
                $queries[$table] = $db->alterSchema([$table => $diffSchema[$table]], $table);
            }

            if (isset($diffSchema[$table]['create'])) {
                $queries[$table] = $db->createSchema($newSchema, $table);
            }
        }

        // Execute all queries generated from diff
        foreach ($queries as $table => $query) {
            try {
                $db->execute($query);
            } catch (PDOException $e) {
                $this->log('MYSQL Schema Update : ' . $e->getMessage());
                return false;
            }
        }

        // Remove cache
        App::uses('Folder', 'Utility');
        $folder = new Folder(ROOT . DS . 'app' . DS . 'tmp' . DS . 'cache');
        if (!empty($folder->path)) {
            $folder->delete();
        }

        // Hook method to update databases data if needed
        $updateEntries = [];
        include ROOT . DS . 'app' . DS . 'Config' . DS . 'Schema' . DS . 'update-entries.php';
        $schema->after([], false, $updateEntries);
        //if update fail, include modify.php
        if (file_exists(ROOT . DS . 'modify.php')) {
            try {
                include(ROOT . DS . 'modify.php');
            } catch (Exception $e) {
                $this->log('Error on update (execute modify.php) - ' . $e->getMessage());
            }
            unlink(ROOT . DS . 'modify.php'); // on le supprime
        }

        // End the update
        return true;
    }
}
