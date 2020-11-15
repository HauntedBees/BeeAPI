# BeeAPI

## wut
A basic Web API foundation handling routing, database querying, authentication, etc.

## dependencies
The optional `OauthTwitterHandler` class depends on [twitteroauth](https://github.com/abraham/twitteroauth), and is included in the project with [composer](https://getcomposer.org/).

## license
BeeAPI is licensed with the [GNU Affero General Public License](https://www.gnu.org/licenses/agpl-3.0.en.html).

## want to make changes?
It's not done yet gimme a minute.

## setup
`API.php` is the main endpoint for this API, and accepts requests in the format of `yourURL.com/API.php?controller=[controllerprefix]&method=[rootmethodname]&param=[parameters]`. Rewrite rules can standardize this, like the following two which will instead use  `API/[controllerprefix]/[rootmethodname]/[parameters]`:

IIS:
```
<rewrite>
    <rules>
        <rule name="BeeCleaner">
            <match url="API/([A-Za-z]+)/([A-Za-z]+)/(.*)" />
            <action type="Rewrite" url="API.php?controller={R:1}&amp;method={R:2}&amp;param={R:3}" />
        </rule>
    </rules>
</rewrite>
```
.htaccess:
```
RewriteEngine On
RewriteRule ^API/([^/]*)/([^/]*)/(.*)$ /API.php?controller=$1&method=$2&param=$3 [L]
```

A `config.ini` file should be created with the same structure as `example_config.ini`. It is recommended that this be placed in a secure location and not at the root of the directory in a production environment, in which case the `CONFIG_PATH` value in **API.php** should be updated.

**BeeAPI** is meant to be modular, and new directories containing Controllers and helper files can be dropped into the root directory; the following conditions should be met:
-  a `Module.php` file should be present in the root of the directory which includes required files.
- all controllers must be prefixed with the name of the directory (i.e. if the folder name is "Test", valid controller names include "TestController" and "TestSomethingController."
- `config.ini` should be updated with a `db_newname` section containing information to connect to a new database, if needed.
 
Examples of this can be seen in the BeeQuestions.API and Sonic.API repositories.

Controllers and methods follow naming conventions - a `GET` request to `API.php?controller=BaseballBat&method=Swing&param=["Bobby", 5]` would expect a `BaseballBatController` class to exist in a `Baseball` directory/module, with a `public function GetSwing(string $swinger, int $velocity)` method belonging to it. Module names are found by splitting controller names (`BaseballBat`) into separate parts on capital letters (`Baseball` and `Bat`) and picking the first one to be the module name. Method names will be prefixed with the HTTP request type  (`GetSwing`, `PostValues`, `DeleteFile`). Parameters should be passed in the form of a stringified JSON array for `GET` and `DELETE` requests, or as a stringified JSON object for `POST` requests.

## parts

###  BeeDB
A class for doing database operations on a MySQL database. 
### BeeResponse
A class for returning proper HTTP responses for JSON data.
### BeeAuth
Handles logins and authentication using the **BeeAPI** schema and JWT tokens.
### BeeController
A base controller class for your controllers to inherit from.
### BeeSecureController
A base controller class that inherits from **BeeController** and requires a valid JWT token.
### Oauth Module
Adds external OAuth provider integration into **BeeAuth**. Currently only Twitter integration is built in.
### API.php
Takes the `controller`, `method`, and `param` querystring parameters and executes the relevant method after ensuring that all parameters are the correct types (for both custom class objects and primitives like `int` and `string`) and that the controller and method actually exist. Autoloading is used based on the Module conventions described above to prevent the need to manually reference controllers.