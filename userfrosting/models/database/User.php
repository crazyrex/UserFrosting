<?php

namespace UserFrosting;

use \Illuminate\Database\Capsule\Manager as Capsule;

/**
 * User Class
 *
 * Represents a User object as stored in the database.
 *
 * @package UserFrosting
 * @author Alex Weissman
 * @see http://www.userfrosting.com/tutorials/lesson-3-data-model/
 *
 * @property int id
 * @property string user_name
 * @property string display_name
 * @property string email
 * @property string title
 * @property string locale
 * @property int primary_group_id
 * @property int secret_token
 * @property int flag_verified
 * @property int flag_enabled
 * @property int flag_password_reset
 * @property timestamp created_at
 * @property timestamp updated_at
 * @property string password
 */
class User extends UFModel {
    
    /**
     * @var string The id of the table for the current model.
     */ 
    protected static $_table_id = "user";    
    /**
     * @var int[] An array of group_ids to which this user belongs. An empty array means that the user's groups have not been loaded yet.
     */
    protected $_groups;
    /**
     * @var Group The primary group for the user.  TODO: separate groups from roles
     */
    protected $_primary_group;  
    
    /**
     * @var bool Enable timestamps for Users.
     */ 
    public $timestamps = true;    
    
    /**
     * Create a new User object.
     *
     */
    public function __construct($properties = [], $id = null) {    
        // Set default locale, if not specified
        if (!isset($properties['locale']))
            $properties['locale'] = static::$app->site->default_locale;
            
        parent::__construct($properties);
    }
    
    /**
     * Determine whether or not this User object is a guest user (id set to `user_id_guest`) or an authenticated user.
     *
     * @return boolean True if the user is a guest, false otherwise.
     */ 
    public function isGuest(){
        if (!isset($this->id) || $this->id === static::$app->config('user_id_guest'))
            return true;
        else
            return false;
    }
    
    /**
     * @todo
     */ 
    public static function isLoggedIn(){
        // TODO.  Not sure how to implement this right now.  Flag in DB?  Or, check sessions?
    }
    
    /**
     * Refresh the User and their associated Groups from the DB.
     *
     * @see http://stackoverflow.com/a/27748794/2970321
     */
    public function fresh(array $options = []){
        // TODO: Update table and column info, in case it has changed?
        $user = parent::fresh($options);
        $user->getGroupIds();
        $user->_primary_group = $user->fetchPrimaryGroup();      
        return $user;
    }
    
    /**
     * Determine if the property for this object exists. 
     *
     * Every property in __get must also be implemented here for Twig to recognize it.
     * @param string $name the name of the property to check.
     * @return bool true if the property is defined, false otherwise.
     */ 
    public function __isset($name) {
        if ($name == "primary_group" || $name == "theme" || $name == "icon" || $name == "landing_page")
            return isset($this->_primary_group);
        else if (in_array($name, ["last_sign_in_event", "last_sign_in_time", "sign_up_time", "last_password_reset_time", "last_verification_request_time"]))
            return true;
        else
            return parent::__isset($name);
    }
    
    /**
     * Get a property for this object.
     *
     * @param string $name the name of the property to retrieve.
     * @throws Exception the property does not exist for this object.
     * @return string the associated property.
     */
    public function __get($name){
        if ($name == "last_sign_in_event")
            return $this->lastEvent('sign_in');
        else if ($name == "last_sign_in_time")
            return $this->lastEventTime('sign_in');
        else if ($name == "sign_up_time")
            return $this->lastEventTime('sign_up');
        else if ($name == "last_password_reset_time")
            return $this->lastEventTime('password_reset_request');
        else if ($name == "last_verification_request_time")
            return $this->lastEventTime('verification_request');
        else if ($name == "primary_group")
            return $this->getPrimaryGroup();
        else if ($name == "theme")
            return $this->getPrimaryGroup()->theme;
        else if ($name == "icon")
            return $this->getPrimaryGroup()->icon;
        else if ($name == "landing_page")
            return $this->getPrimaryGroup()->landing_page;
        else
            return parent::__get($name);
    }
    
    /**
     * Extends Eloquent's Collection models.
     */
    public function newCollection(array $models = Array()) {
	    return new UserCollection($models);
    }
    
    /**
     * Get all events for this user.
     */    
    public function events(){
        return $this->hasMany('UserFrosting\UserEvent');
    }
    
    /**
     * Get the most recent time for a specified event type for this user.
     *
     * @return string|null The last event time, as a SQL formatted time (YYYY-MM-DD HH:MM:SS), or null if an event of this type doesn't exist.
     */     
    public function lastEventTime($type){
        $result = $this->events()
        ->where('event_type', $type)
        ->max('occurred_at');
        return $result ? $result : null;
    }
    
    /**
     * Get the most recent event of a specified type for this user.
     *
     * @return UserEvent
     */    
    public function lastEvent($type) {
        return $this->events()
        ->where('event_type', $type)
        ->orderBy('occurred_at', 'desc')
        ->first();
    }    
     
    /**
     * Get an array containing all groups to which this user belongs.
     *
     * 
     * @return Group[] An array of Group objects, indexed by the group id.
     */       
    public function groups(){
        // First, sync any cached groups
        $this->syncCachedGroups();
            
        $link_table = Database::getSchemaTable('group_user')->name;
        return $this->belongsToMany('UserFrosting\Group', $link_table);
    }
    
    /**
     * Sync the database relations with the Groups in $this->_groups, if it has been set.
     *
     * This method DOES modify the database.
     */
    private function syncCachedGroups(){
        if (isset($this->_groups)) {
            $link_table = Database::getSchemaTable('group_user')->name;
            return $this->belongsToMany('UserFrosting\Group', $link_table)->sync($this->_groups);
        } else
            return false;
    }

    /**
     * Get the groups to which this User currently belongs, as currently represented in this object.
     *
     * This method caches the data after the first time loading from the database.  To force a refresh, use the `fresh` method.
     * @return array[Group] An array of Group objects, indexed by group_id, to which this User belongs.
     */
    public function getGroups(){
        $this->getGroupIds();
        
        // Return the array of group objects
        $result = Group::find($this->_groups);
        
        $groups = [];
        foreach ($result as $group){
            $groups[$group->id] = $group;
        }
        return $groups;        
    }
    
    /**
     * Get an array of group_ids to which this User currently belongs, as currently represented in this object.
     *
     * This method does NOT modify the database.
     * @return array[int] An array of group_ids to which this User belongs.
     */    
    public function getGroupIds(){
        // Fetch from database, if not set
        if (!isset($this->_groups)){    
            $link_table = Database::getSchemaTable('group_user')->name;
            $result = Capsule::table($link_table)->select("group_id")->where("user_id", $this->id)->get();
            
            $this->_groups = [];
            foreach ($result as $group){
                $this->_groups[] = $group['group_id'];
            }      
        }
        
        return $this->_groups;
    }
    
    /**
     * Add a group_id to the list of _groups to which this User belongs, checking that the Group exists and the User isn't already a member.
     *     
     * This method does NOT modify the database.  Call `store` to persist to database. 
     * @param int $group_id The id of the group to add the user to.
     * @throws Exception The specified group does not exist.
     * @return User this User object.
     */  
    public function addGroup($group_id){
        $this->getGroupIds();
        
        // Return if user already in group
        if (in_array($group_id, $this->_groups))
            return $this;
        
        // Next, check that the requested group actually exists
        if (!Group::find($group_id))
            throw new \Exception("The specified group_id ($group_id) does not exist.");
                
        // Ok, add to the list of groups
        $this->_groups[] = $group_id;
        
        return $this;        
    }
    
    /**
     * Remove a group_id from the list of _groups to which this User belongs, checking that the User is already a member.
     *
     * This method does NOT modify the database. Call `store` to persist to database.
     * @param int $group_id The id of the group to remove the user from.
     * @return User this User object.
     */   
    public function removeGroup($group_id){
        // Fetch from database, if not set
        $this->getGroupIds();
        
        // Check that user not in group     
        if (($key = array_search($group_id, $this->_groups)) !== false) {
            unset($this->_groups[$key]);
        }
        
        return $this;           
    
    }

    /**
     * Get the theme for this user.
     *
     * The theme for the root user is always 'root'.  The theme for guest users is 'default'.  Any other users will have their themes determined by their primary group.
     * @return string The theme for this user.
     */ 
    public function getTheme(){
        if (!isset($this->id) || $this->id == static::$app->config('user_id_guest'))
            return "default";
        else if ($this->id == static::$app->config('user_id_master'))
            return "root";
        else
            return $this->getPrimaryGroup()->theme;
    }
    
    /**
     * Get this user's primary group.
     *
     * This method caches the data after the first time loading from the database.  To force a refresh, use the `fresh` method.
     * @return Group the Group object representing the user's primary group.
     */  
    public function getPrimaryGroup(){
        if (!isset($this->_primary_group)) {
            $this->_primary_group = $this->fetchPrimaryGroup();
        }
        
        return $this->_primary_group;
    }
    
    /**
     * Fetch the primary group that this User belongs to from the database
     *
     * @return Group|false
     */
    private function fetchPrimaryGroup() {
        if (!isset($this->primary_group_id)){
            throw new \Exception("This user does not appear to have a primary group id set.");
        }
        return $this->belongsTo('UserFrosting\Group', 'primary_group_id')->getEager()->first();
    }
 
    /**
     * Store the User to the database, along with any group associations, updating as necessary.
     *
     */
    public function save(array $options = [], $force_create = false){       
        // Update the user record itself
        $result = parent::save($options);
        
        // Synchronize model's group relations with database
        $this->syncCachedGroups();
        
        return $result;
    }
    
    /**
     * Create an event saying that this user registered their account, or an account was created for them.
     * 
     * @param User $creator optional The User who created this account.  If set, this will be recorded in the event description.
     */
    public function newEventSignUp($creator = null){
        if ($creator)
            $description = "User {$this->user_name} was created by {$creator->user_name} on " . date("Y-m-d H:i:s") . ".";
        else
            $description = "User {$this->user_name} successfully registered on " . date("Y-m-d H:i:s") . ".";
        $event = new UserEvent([
            "user_id"     => $this->id,
            "event_type"  => "sign_up",
            "description" => $description
        ]);
        return $event;
    }
    
    /**
     * Create a new user sign-in event.
     * 
     */
    public function newEventSignIn(){    
        return new UserEvent([
            "user_id"     => $this->id,
            "event_type"  => "sign_in",
            "description" => "User {$this->user_name} signed in at " . date("Y-m-d H:i:s") . "."
        ]);
    }
    
    /**
     * Create a new email verification request event.  Also, generates a new secret token.
     * 
     */
    public function newEventVerificationRequest(){
        $this->secret_token = User::generateActivationToken();
        $event = new UserEvent([
            "user_id"     => $this->id,
            "event_type"  => "verification_request",
            "description" => "User {$this->user_name} requested verification on " . date("Y-m-d H:i:s") . "."
        ]);
        return $event;
    }    
    
    /**
     * Create a new password reset request event.  Also, generates a new secret token.
     * 
     */
    public function newEventPasswordReset(){
        $this->secret_token = User::generateActivationToken();
        $this->flag_password_reset = "1";
        $event = new UserEvent([
            "user_id"     => $this->id,
            "event_type"  => "password_reset_request",
            "description" => "User {$this->user_name} requested a password reset on " . date("Y-m-d H:i:s") . "."
        ]);
        return $event;
    } 
    
    /**
     * Delete this user from the database, along with any linked groups and authorization rules
     *
     * @return bool true if the deletion was successful, false otherwise.
     */
    public function delete(){        
        // Remove all group associations
        $this->groups()->detach();
        
        // Remove all user auth rules
        $auth_table = Database::getSchemaTable('authorize_user')->name;
        Capsule::table($auth_table)->where("user_id", $this->id)->delete();
        
        // Remove all user events
        $event_table = Database::getSchemaTable('user_event')->name;
        Capsule::table($event_table)->where("user_id", $this->id)->delete();
        
        // Delete the user        
        $result = parent::delete();
        
        return $result;
    }
    
    /**
     * Checks whether or not this user has access for a particular authorization hook.
     *
     * Determine if this user has access to the given $hook under the given $params.
     * @param string $hook The authorization hook to check for access.
     * @param array $params[optional] An array of field names => values, specifying any additional data to provide the authorization module
     * when determining whether or not this user has access.
     * @return boolean True if the user has access, false otherwise.
     */ 
    public function checkAccess($hook, $params = []){
        if ($this->isGuest()){   // TODO: do we sometimes want to allow access to protected resources for guests?  Should we model a "guest" group?
            return false;
        }
    
        // The master (root) account has access to everything.
        if ($this->id == static::$app->config('user_id_master'))
            return true;
             
        // Try to find an authorization rule for $hook that matches the currently logged-in user, or one of their groups.
        $rule = UserAuth::fetchUserAuthHook($this->id, $hook);
        
        if (empty($rule))
            $pass = false;
        else {      
            $ace = new AccessConditionExpression(static::$app); // TODO: should we have to pass the app in, or just make it available statically?
            $pass = $ace->evaluateCondition($rule['conditions'], $params);
        }
        
        // If no user-specific rule is passed, look for a group-level rule
        if (!$pass){
            $ace = new AccessConditionExpression(static::$app);
            $groups = $this->getGroupIds();
            foreach ($groups as $group_id){
                // Try to find an authorization rule for $hook that matches this group
                $rule = GroupAuth::fetchGroupAuthHook($group_id, $hook);
                if (!$rule)
                    continue;
                $pass = $ace->evaluateCondition($rule['conditions'], $params);
                if ($pass)
                    break;
            }
        }
        return $pass;
    }
 
    /**
     * Verify a plaintext password against the user's hashed password.
     *
     * @param string $password The plaintext password to verify.
     * @return boolean True if the password matches, false otherwise.
     */   
    public function verifyPassword($password){
        if (Authentication::getPasswordHashType($this->password) == "sha1"){
            $salt = substr($this->password, 0, 25);		// Extract the salt from the hash
            $hash_input = $salt . sha1($salt . $password);
            if ($hash_input == $this->password){
                return true;
            } else {
                return false;
            }
        }	
        // Homegrown implementation (assuming that current install has been using a cost parameter of 12)
        else if (Authentication::getPasswordHashType($this->password) == "homegrown"){
            /*used for manual implementation of bcrypt*/
            $cost = '12'; 
            if (substr($this->password, 0, 60) == crypt($password, "$2y$".$cost."$".substr($this->password, 60))){
                return true;
            } else {
                return false;
            }
        // Modern implementation
        } else {
            return password_verify($password, $this->password);
        }    
    }
    
    /**
     * Log this user in.  This basically updates the user's sign-in time, and updates any old password hashes.
     *
     * You assign this user's id to $_SESSION["userfrosting"]["user"] after calling login, so that it will persist in the session.
     */
    public function login(){    
        // Add a sign in event (time is automatically set by database)
        $event = $this->newEventSignIn();
        $event->save();
        
        // Update password if we had encountered an outdated hash
        if (Authentication::getPasswordHashType($this->password) != "modern"){
            // Hash the user's password and update
            $password_hash = Authentication::getPasswordHashType($password);
            if ($password_hash === null){
                error_log("Notice: outdated password hash could not be updated because the new hashing algorithm is not supported.  Are you running PHP >= 5.3.7?");
            } else {
                $this->password = $password_hash;
                error_log("Notice: outdated password hash has been automatically updated to modern hashing.");
            }
        }
        
        // Store changes
        $this->store();
        
        return $this;
    }
    
    /**
     * Generate an activation token for a user.
     *
     * This generates a token to use for activating a new account, resetting a lost password, etc.
     * @param string $gen specify an existing token that, if we happen to generate the same value, we should regenerate on.
     * @return string
     */
    public static function generateActivationToken($gen = null) {
        do {
            $gen = md5(uniqid(mt_rand(), false));
        } while(User::where('secret_token', $gen)->first());
        return $gen;
    }    
    
}
