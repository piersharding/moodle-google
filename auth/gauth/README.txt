Google OpenId Authentication for Moodle
-------------------------------------------------------------------------------
license: http://www.gnu.org/copyleft/gpl.html GNU Public License

Changes:
- 2011-10    : Created by Piers Harding

Requirements:
- None

Notes: 

Install instructions:
  1. unpack this archive into the / directory as you would for any Moodle
     auth module (http://docs.moodle.org/en/Installing_contributed_modules_or_plugins).
  2. Login to Moodle as an administrator, and activate the module by navigating
     Administration -> Plugins -> Authentication -> Manage authentication and clicking on the enable icon.
  3. Configure the settings for the plugin - pay special attention to the
     mapping of the Moodle and Google username attribute.
- 4. If you only want auth/gauth as login option, change login page to point to auth/gauth/index.php
- 5. If you want to use another authentication method together with auth/gauth, 
    in parallel, change the 'Instructions' in the 'Common settings' of the
    'Administrations >> Users >> Authentication Options' to contain a link to the
    auth/gauth login page (-- remember to check the href and src paths --):
    <br>Click <a href="auth/gauth/index.php">here</a> to login with SSO
- 6 Save the changes for the 'Common settings'

