<?php

global $prefix;
include_once("$prefix/SectionBase.php");
include_once("$prefix/User.php");

class SectionAccount extends SectionBase
{

var $message;
var $section;
var $user;

function SectionAccount()
{
  $this->name="account";
  $this->message='';
  $this->section='';
  $this->user='';
}

/** Checks the username for validity. 
  The Username must start with an letter, followed by letters, numbers, or
  special characters (-, _, ., @). All letters must be lowered.
  
  @param name Username to check
  @return true if the name is possible, an error string of the error message
  otherwise */
function check_username($name)
{
  if (strlen($name)<4)
    return "The username is to short. It must have at least 4 characters";
  if (strlen($name)>32)
    return "The username is to long. Maximum length is 32 characters";
    
  if (!preg_match('/^[a-z][a-z0-9\-_\.\@]+$/', $name))
  {
    return "Username contains invalid characters. The name must start with an letter, followed by letters, numbers, or characters of '-', '_', '.', '@'.";
  }

  global $db;
  $sql="SELECT name 
        FROM $db->user
        WHERE name='$name'";
  $result=$db->query($sql);
  if (mysql_num_rows($result)>0)
  {
    return "The username is already taken";
  }
  return true;
}

/** Creats a new user.
  @param name Name of the new user
  @param password password of the new user
  @return true on success, false otherwise */
function user_create($name, $password)
{
  global $db;
  $result=$this->check_username($name);
  if (!is_bool($result) || $result==false)
  {
    $this->warning("Sorry, the username '$name' could not be created. $result");
    return false;
  }
  
  $sql="INSERT INTO 
        $db->user ( 
          name, password, email
        ) VALUES (
          '$name', '$password', 'email'
        )";
  if (!$db->query($sql))
    return false;
    
  return true;
}

function print_form_new()
{
  echo "<form method=\"post\">
<table>
  <tr><td>Username:</td><td><input type=\"text\" name=\"name\" value=\"$this->user\"/><td></tr>
  <tr><td>Password:</td><td><input type=\"password\" name=\"password\"/><td></tr>
  <tr><td>Confirm:</td><td><input type=\"password\" name=\"confirm\"/><td></tr>
  <tr><td>Email:</td><td><input type=\"text\" name=\"email\"/><td></tr>
  <tr><td></td>
      <td><input type=\"submit\" value=\"Create\"/>&nbsp;&nbsp;
      <input type=\"reset\" value=\"Reset\"/></td></tr>
</table>

<input type=\"hidden\" name=\"section\" value=\"account\" />
<input type=\"hidden\" name=\"action\" value=\"create\" />
</form>";
}

/** Delte all data from a user
  @todo ensure to delete all data from the user */
function _delete_user_data($id)
{
  global $db;

  // delete all tags
  $sql="DELETE $db->tag 
        FROM $db->tag, $db->image
        WHERE $db->image.userid=$id AND $db->image.id=$db->tag.imageid";
  $db->query($sql);

  // Delete cached image data
  $sql="SELECT id 
        FROM $db->image
        WHERE id=$id";
  $result=$db->query($sql);
  if (!$result)
    return;

  while ($row=mysql_fetch_assoc($result))
  {
    // @todo delete all cached data
  }
  
  // Delete all image data
  $sql="DELETE $db->image 
        FROM $db->image
        WHERE id=$id";
  $result=$db->query($sql);

  // Delete all preferences
  $sql="DELETE $db->pref
        FROM $db->pref
        WHERE userid=$id";
  $result=$db->query($sql);
  
  // @todo delete the group of the user
  // @todo delete users upload directory
  
  // Delete the user data
  $sql="DELETE $db->user
        FROM $db->user
        WHERE id=$id";

  $result=$db->query($sql);
  return true;
}

/** Delete a specific user */
function user_delete($id=-1)
{
  global $user;
  global $db;

  if ($id>0)
  {
    if (!$user->is_admin())
    {
      $this->warning("You are not allowed to delete the user");
      return false;
    }
    if ($this->_delete_user_data($id))
      $this->info("User was deleted successfully");
    return;
  }

  $id=$user->get_userid();
  if ($this->_delete_user_data($id))
    $this->info("Your Account was deleted successfully");
  return;
}

function print_delete_account()
{
  echo "<h2>Delete Account</h2>\n";
  echo "<form section=\"index.php\" method=\"post\">
<table>
  <tr><td>Username:</td><td><input type=\"text\" name=\"user\"/><td></tr>
  <tr><td>Password:</td><td><input type=\"password\" name=\"password\"/><td></tr>
  <tr><td>Confirm:</td><td><input type=\"password\" name=\"confirm\"/><td></tr>
</table>
<input type=\"hidden\" name=\"section\" value=\"account\" />
<input type=\"hidden\" name=\"action\" value=\"delete\" />
</form>";
}

function print_login()
{
  echo "<h2>Login</h2>\n";
  /*
  if ($_REQUEST['user']!='' && $_REQUEST['password']!='')
  {
    $user = new User();
    if ($user->check_login($_REQUEST['user'], $_REQUEST['password']))
    {
      echo "Login succeed.</br>\n";
      return;
    }
  }
  */
  if ($this->message!='') 
  {
    $this->warning($this->message);
  }
  echo "<form section=\"index.php\" method=\"post\">
<table>
  <tr><td>Username:</td><td><input type=\"text\" name=\"user\"/><td></tr>
  <tr><td>Password:</td><td><input type=\"password\" name=\"password\"/><td></tr>
  <tr><td></td>
      <td><input type=\"submit\" value=\"Login\"/>&nbsp;&nbsp;
      <input type=\"reset\" value=\"Reset\"/></td></tr>
</table>

<input type=\"hidden\" name=\"section\" value=\"account\" />
<input type=\"hidden\" name=\"action\" value=\"login\" />\n";
  if ($this->section!='')
  {
    echo "<input type=\"hidden\" name=\"pass-section\" value=\"$this->section\" />\n";
  }
echo "</form>";

  //echo "<a href=\"index.php?section=account&action=new\">Create Account</a><br/>\n";
}

function print_user_list()
{
  global $db;
  $sql="SELECT *
        FROM $db->user";

  $result=$db->query($sql);
  if (!$result)
    return;

  echo "<table>
  <tr>
    <th></td>
    <th>Name</th>
    <th>Actions</th>
  </tr>\n";
  $delete="index.php?section=account&amp;action=delete&amp;id=";
  while ($row=mysql_fetch_assoc($result))
  {
    echo "  <tr>
    <td><input type=\"checkbox\"></td>
    <td>${row['name']}</td>
    <td><a href=\"${delete}${row['id']}\">delete</a></td>
  </tr>\n";
  }
  echo "</table>\n";
}
function print_content()
{
  global $db;
  global $user;
  
  $action=$_REQUEST['action'];
  if ($action=='create')
  {
    echo "<h2>Create A New Account</h2>\n";
    $name=$_REQUEST['name'];
    $password=$_REQUEST['password'];
    $confirm=$_REQUEST['confirm'];
    if ($password != $confirm) 
    {
      $this->error("Password mismatch");
      return;
    }
    if ($this->user_create($name, $password)==true)
    {
      $this->success("User '$name' created");
    }
    else
      $this->print_form_new();
    return;
  }
  else if ($action=='new')
  {
    echo "<h2>Create A New Account</h2>\n";
    $this->print_form_new();
  } else if ($action=='list')
  {
    if ($user->is_admin())
      $this->print_user_list();
  } else if ($action=='delete')
  {
    if (isset($_REQUEST['id']))
      $this->user_delete($_REQUEST['id']);
    else
      $this->user_delete(-1);
  }
  if ($action=='login')
  {
    $this->print_login();
  }

  if ($user->is_admin())
    echo "<a href=\"index.php?section=account&amp;action=list\">List users</a>\n";
}

}
?>
