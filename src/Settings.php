<?php

namespace hackerESQ\Settings;

use DB;
use Cache;

class Settings
{
    /*
    *   Install:
    *
    *   Config/app.php:
    *       
    *       'providers' => [
    *           App\CaseTime\Providers\SettingsServiceProvider::class,
    *       ]
    *
    *       'aliases' => [
    *           'Settings' => App\Settings::class,
    *       ]
    *
    *   Usage:
    *
    *       Settings::set(['firm_name'=>'new']);
    *       Settings::get();
    *       Settings::get('firm_name');
    *       Settings::get(['firm_name','contact_types']);
    */

    public function resolveCache() {
        return Cache::rememberForever('settings', function () {
            return DB::table('settings')->pluck('value', 'key')->toArray();
        });
    }

    public function decryptHandler($settings) {

        // DO WE NEED TO DECRYPT ANYTHING?
        foreach ($settings as $setting => $value) {
            if (in_array($setting, config('settings.encrypt'))) {
                array_set($settings, $setting, decrypt($settings[$setting]));
            }
        }

        return $settings;

    }

    public function get($key = NULL)
    {
        $settings = $this->decryptHandler($this->resolveCache());

        // no key passed, assuming get all settings
        if ($key == NULL) {
            
            return $settings;
        }
        
        // array of keys passed, return those settings only
        if (is_array($key)) {
            foreach ($key as $key) {
                $result[] = $settings[$key];
            }
            return $result;
        }

        // single key passed, return that setting only
        if (array_key_exists($key, $settings)) {

            return $settings[$key]; 
        } 

        return false;
        
    }

    public function set($changes, bool $force = false)
    {

        // when saving updates back to DB, must save in JSON for contact_types
        // $json = json_encode(preg_split ('/$\R?^/m', $contact_types));

        // DO WE NEED TO ENCRYPT ANYTHING?
        foreach ($changes as $key => $value) {
            if (in_array($key, config('settings.encrypt'))) {
                array_set($changes, $key, encrypt($value));
            }
        }

        // ARE WE FORCING? OR SHOULD WE BE SECURE?
        if ($force) {

            foreach ($changes as $key => $value) {

                DB::table('settings')->where('key', '=', $key)->delete();    
                DB::table('settings')->insert(['key'=>$key,'value'=>$value]); 
            }

        } else {

            $settings = $this->resolveCache();

            // array_only() - will return only the specified key-value pairs from the given array
                // array_keys() - will return all the keys or a subset of the keys of an array
                    // this passes array_keys() to array_only() to give current/valid settings only
                        //checks and see if passed settings are  valid options
            foreach (array_only($changes, array_keys($settings)) as $key => $value) {
                DB::table('settings')->where('key', $key)->update(['value'=>$value]); 
            }
        }

        Cache::forget('settings');

        return true;

    }

}