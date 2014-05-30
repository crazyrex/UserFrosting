<?php
/*

UserFrosting Version: 0.2.0
By Alex Weissman
Copyright (c) 2014

Based on the UserCake user management system, v2.0.2.
Copyright (c) 2009-2012

UserFrosting, like UserCake, is 100% free and open-source.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the 'Software'), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

require_once("db_functions.php");

/******************************************************************************************************************

Secured functions.  These functions will automatically check the logged in user's permissions against the permit
database before proceeding.

*******************************************************************************************************************/

// Load data for specified user
function loadUser($user_id){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return fetchUser($user_id);
}

// Load data for all users.  TODO: allow filtering by group membership  TODO: also load group membership
function loadUsers($limit = NULL){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    try {
      global $db_table_prefix;
      
      $results = array();
      
      $db = pdoConnect();
        
      $sqlVars = array();
      
      $query = "select {$db_table_prefix}users.id as user_id, user_name, display_name, email, title, sign_up_stamp, last_sign_in_stamp, active, enabled from {$db_table_prefix}users";    
      
      $stmt = $db->prepare($query);
      $stmt->execute($sqlVars);
      
      if (!$limit){
          $limit = 9999999;
      }
      $i = 0;
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC) and $i < $limit) {
          $id = $r['user_id'];
          $results[$id] = $r;
          $i++;
      }
      
      $stmt = null;
      return $results;
    
    } catch (PDOException $e) {
      addAlert("danger", "Oops, looks like our database encountered an error.");
      error_log("Error in " . $e->getFile() . " on line " . $e->getLine() . ": " . $e->getMessage());
      return false;
    }
}

//Change a user from inactive to active based on their user id
function activateUser($user_id) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    try {
        global $db_table_prefix;
      
        $db = pdoConnect();
      
        $sqlVars = array();
      
        $query = "UPDATE ".$db_table_prefix."users
            SET active = 1
            WHERE
            id = :user_id
            LIMIT 1";
        
        $stmt = $db->prepare($query);
        $sqlVars[':user_id'] = $user_id;
        $stmt->execute($sqlVars);
        
        if ($stmt->rowCount() > 0)
          return true;
        else {
          addAlert("danger", "Invalid user id specified.");
          return false;
        }
    
    } catch (PDOException $e) {
      addAlert("danger", "Oops, looks like our database encountered an error.");
      error_log("Error in " . $e->getFile() . " on line " . $e->getLine() . ": " . $e->getMessage());
      return false;
    }
}

//Update a user's display name
function updateUserDisplayName($user_id, $display_name) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return updateUserField($user_id, 'display_name', $display_name);
}

//Update a user's email
function updateUserEmail($user_id, $email) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return updateUserField($user_id, 'email', $email);
}

//Update a user's title
function updateUserTitle($user_id, $title) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return updateUserField($user_id, 'title', $title);
}

//Update a user's password (hashed value)
function updateUserPassword($user_id, $password) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return updateUserField($user_id, 'password', $password);
}

// Update a user as enabled ($enabled = 1) or disabled (0)
function updateUserEnabled($user_id, $enabled){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    global $master_account;
    // Cannot disable master account
    if ($user_id == $master_account && $enabled == '0'){
        addAlert("danger", lang("ACCOUNT_DISABLE_MASTER"));
        return false;
    }
    
    // Disable the specified user, but leave their information intact in case the account is re-enabled.
    try {

        $db = pdoConnect();
        global $db_table_prefix;
        
        $sqlVars = array();
        
        $query = "UPDATE ".$db_table_prefix."users
            SET
            enabled = :enabled
            WHERE
            id = :user_id
            LIMIT 1";

        $stmt = $db->prepare($query);
        
        $sqlVars[':user_id'] = $user_id;
        $sqlVars[':enabled'] = $enabled;

        $stmt->execute($sqlVars);

        if ($stmt->rowCount() > 0)
            return true;
        else {
            addAlert("danger", "The specified user was not found.");
            return false;
        }
      
    } catch (PDOException $e) {
      addAlert("danger", "Oops, looks like our database encountered an error.");
      error_log("Error in " . $e->getFile() . " on line " . $e->getLine() . ": " . $e->getMessage());
      return false;
    }
}

// Delete a specified user and all of their permission settings.  Returns true on success, false on failure.
function deleteUser($user_id){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return removeUser($user_id);
}

// Load complete information on all user groups.
function loadGroups(){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    // Calls appropriate function in db_functions
    return fetchAllGroups();
}

// Load information for a specified group.
function loadGroup($group_id){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    // Calls appropriate function in db_functions
    return fetchGroupDetails($group_id);
}

// Load group membership for the specified user.
function loadUserGroups($user_id){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    return fetchUserGroups($user_id);
}

//Create a new user group.
function createGroup($name) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }

    //Validate request
    if (groupNameExists($name)){
        addAlert("danger", lang("PERMISSION_NAME_IN_USE", array($name)));
        return false;
    }
    elseif (minMaxRange(1, 50, $name)){
        addAlert("danger", lang("PERMISSION_CHAR_LIMIT", array(1, 50)));
        return false;
    }
    else {
        if (dbCreateGroup($name, 0, 1)) {
            addAlert("success", lang("PERMISSION_CREATION_SUCCESSFUL", array($name)));
            return true;
        } else {
            return false;
        }
    }
}

//Change a group's details
function updateGroup($group_id, $name, $is_default = 0, $can_delete = 1) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }

    //Check if selected group exists
    if(!groupIdExists($group_id)){
        addAlert("danger", "I'm sorry, the group id you specified is invalid!");
        return false;
    }

    $groupDetails = fetchGroupDetails($group_id); //Fetch information specific to group

	//Update group name, if different from previous and not already taken
	$name = trim($name);
    if(strtolower($name) != strtolower($groupDetails['name'])){
        if (groupNameExists($name)) {
            addAlert("danger", lang("ACCOUNT_PERMISSIONNAME_IN_USE", array($name)));
            return false;
		}
		elseif (minMaxRange(1, 50, $name)){
			addAlert("danger", lang("ACCOUNT_PERMISSION_CHAR_LIMIT", array(1, 50)));
            return false;
		}
    }
    
    if (dbUpdateGroup($group_id, $name, $is_default, $can_delete)){
		addAlert("success", lang("PERMISSION_NAME_UPDATE", array($name)));
        return true;
    }
    else {
        return false;
    }    
}

//Delete a user group
function deleteGroup($group_id) {
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }
    
    try {

        $db = pdoConnect();
        global $db_table_prefix;
        
        $groupDetails = fetchGroupDetails($group_id);
	
        if ($groupDetails['can_delete'] == '0'){
            addAlert("danger", lang("CANNOT_DELETE_PERMISSION_GROUP", array($groupDetails['name'])));
            return false;
        }
	
        $stmt = $db->prepare("DELETE FROM ".$db_table_prefix."groups 
            WHERE id = :group_id");
        
        $stmt2 = $db->prepare("DELETE FROM ".$db_table_prefix."user_group_matches 
            WHERE group_id = :group_id");
        
        $stmt3 = $db->prepare("DELETE FROM ".$db_table_prefix."group_page_matches 
            WHERE group_id = :group_id");
        
        $sqlVars = array(":group_id" => $group_id);
        
        $stmt->execute($sqlVars);
        
        if ($stmt->rowCount() > 0) {
            // Delete user and page matches for this group.
            $stmt2->execute($sqlVars);
            $stmt3->execute($sqlVars);
            return $groupDetails['name'];
        } else {
            addAlert("danger", "The specified group does not exist.");
            return false;
        }      
    } catch (PDOException $e) {
      addAlert("danger", "Oops, looks like our database encountered an error.");
      error_log("Error in " . $e->getFile() . " on line " . $e->getLine() . ": " . $e->getMessage());
      return false;
    }
}

// Retrieve an array containing all site configuration parameters
function loadConfigParameters(){
    // This block automatically checks this action against the permissions database before running.
    if (!checkActionPermissionSelf(__FUNCTION__, func_get_args())) {
        addAlert("danger", "Sorry, you do not have permission to access this resource.");
        return false;
    }

    return fetchConfigParameters();
}

?>