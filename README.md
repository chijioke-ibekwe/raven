
# <div align="center">Messenger</div>
<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/chijioke-ibekwe/The-Documentation-Compendium.svg)](https://github.com/chijioke-ibekwe/messenger/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/chijioke-ibekwe/The-Documentation-Compendium.svg)](https://github.com/chijioke-ibekwe/messenger/pulls)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](/LICENSE)

</div>

---

<p align="center"> Multi-channel Laravel notification sender
    <br> 
</p>

## 📝 Table of Contents

- [About](#about)
- [Getting Started](#getting_started)
- [Usage](#usage)
- [Built Using](#built_using)
- [TODO](#todo)
- [Authors](#authors)

## 🧐 About <a name = "about"></a>
Messenger is a Laravel package that handles sending notifications through multiple channels in a project, and allows you to 
focus on more important parts of your business logic. Currently, Messenger supports sending email notifications (through Sendgrid) 
and database notifications. SMS notifications support will be integrated soon.

## 🏁 Getting Started <a name = "getting_started"></a>

### Prerequisites
Before setting up this project on your local machine, you need the following requirements:

1. PHP v8.2.4
2. Composer v2.5.5

NB: versions may vary

### Setting up for Local Development
To set up the project:

- Fork the repository

- Clone the repository using the command:
    ```bash
    git clone https://github.com/<your-github-username>/messenger.git
    ```

- Install dependencies using the command:
    ```bash
    composer install
    ```

- And, run tests using the command:
    ```bash
    composer test
    ```

## 🎈 Usage <a name="usage"></a>
This package is not available on Packagist. Hence, to use this package in your laravel project: 
1. Add the following sections to your project's `composer.json` file:
    
    ```json
    {
      "require": {
        "chijioke-ibekwe/messenger": "1.0.0"
      }
    }
    ```
    ```json
    {
      "repositories": [
        {
          "type": "vcs",
          "name": "chijioke-ibekwe/messenger",
          "url": "https://github.com/chijioke-ibekwe/messenger.git",
          "branch": "1.0.0"
        }
      ]
    }
    ```

2. Then, proceed to run the following command to resolve the dependency:
    ```bash
    composer update
    ```

3. After successfully adding this package to your project, you will need to publish and run the migration files as well 
    as the config file. The following command will allow you do all of the above:
    ```bash
    php artisan messenger:install
    ```

4. The migrations will be published in your project's migrations directory `./database/migrations` while the config file
   `messenger.php`, will be published in your config directory `./config`. Don't forget to customize the config file to suit
    your needs.

5. After the migrations have been run successfully, you can then proceed to add notification contexts to the database.
   To do this, simply create a migration file similar to the ones below:
   - Email Notification Context
    ```php
    <?php
    
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\DB;
    
    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            $id = DB::table('notification_contexts')->insertGetId(
                array(
                    'name' => 'user-verified',
                    'email_template_id' => 'd-ad34ghAwe3mQRvb29',
                    'description' => 'Notification to inform a user that they have been verified on the platform'
                )
            );
    
            DB::table('notification_channel_notification_context')->insert(
                array(
                    'notification_channel_id' => 1, //EMAIL
                    'notification_context_id' => $id
                )
            );
        }
    
        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            DB::table('notification_contexts')->where('name', 'user-verified')->delete();
        }
    };
    
    ```

- Database Notification Context
    ```php
    <?php
    
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\DB;
    
    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            $id = DB::table('notification_contexts')->insertGetId(
                array(
                    'name' => 'user-verified',
                    'description' => 'Notification to inform a user that they have been verified on the platform',
                    'title' => 'You have been verified',
                    'body' => 'Hello {name}. This is to let you know that your account with email {email} has been verified',
                    'type' => 'user'
                )
            );
    
            DB::table('notification_channel_notification_context')->insert(
                array(
                    'notification_channel_id' => 2, //DATABASE
                    'notification_context_id' => $id
                )
            );
        }
    
        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            DB::table('notification_contexts')->where('name', 'user-verified')->delete();
        }
    };
    
    ```

- Email and Database Notification Context
    ```php
    <?php
    
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\DB;
    
    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            $id = DB::table('notification_contexts')->insertGetId(
                array(
                    'name' => 'user-verified',
                    'email_template_id' => 'd-ad34ghAwe3mQRvb29',
                    'description' => 'Notification to inform a user that they have been verified on the platform',
                    'title' => 'You have been verified',
                    'body' => 'Hello {name}. This is to let you know that your account with email {email} has been verified',
                    'type' => 'user'
                )
            );
    
            DB::table('notification_channel_notification_context')->insert(
                array(
                    array(
                        'notification_channel_id' => 1, //EMAIL
                        'notification_context_id' => $id,
                    ),
                    array(
                        'notification_channel_id' => 2, //DATABASE
                        'notification_context_id' => $id,
                    )
                )
            );
        }
    
        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            DB::table('notification_contexts')->where('name', 'user-verified')->delete();
        }
    };
    
    ```

6. To send a notification at any point in your code, build a `NotificationData` object, set the relevant 
   fields as shown below, and dispatch a `MessengerEvent`:

    ```php
            $data = new NotificationData();
            $data->setContextName('user-verified');
            $data->setRecipients($verified_user);
            $data->setCcs(['john.doe@messenger.com' => 'John Doe', 'jane.doe@messenger.com' => 'Jane Doe'])
            $data->setParams([
                'id' => $verified_user->id,
                'name' => $verified_user->name
                'email' => $verified_user->email
            ]);
            $data->setAttachmentUrls($document_url)
    
            event(new MessengerEvent($data));
    ```
    The `contextName` property is required and must match the notification context name for that notification 
    on the database.  
    The `recipients` property is required and takes any single notifiable or an array of notifiables that should receive 
    the notification.
    The `ccs` property is exclusively for email notifications and takes an associative array with a key-value pair of 
    the emails and names of people you want to CC on the email.
    The `params` property is an associative array of all the variables that exist on the notification 
    template with their values, where the key must match the variable name on the template.  
    Finally, the `attachments` field takes a url or an array of urls that point to the publicly accessible resource(s) that 
    needs to be attached to the email notification.  

9. To successfully send Database Notifications, it is assumed that the user of this package has already set up a 
   notifications table in their project via the command below:

    ```bash
    php artisan notifications:table
    ```
    And subsequently:
    ```bash
    php artisan migrate
    ```
    The data column for database notifications using this package, captures the following properties:
    ```php
     [
        'title' => $title,
        'body' => $body,
        'type' => $type,
        'id' => $id
     ];
    ```
    The `title`, `body`, and `type` properties are obtained from the notification context for the said notification on the 
    database. The `id` field which could be used to build a link to the entity in question on the UI, is provided as a param 
    whilst building the `NotificationData` object. 

10. The package takes care of the rest of the logic.

### API
The following API is included in this package for ease of use:
1. `GET /api/v1/notification-contexts`
   - Fetches all notification contexts on the database. The user of this API has to be authenticated.
   - Return a JSON of the format below:
   ```json
    {
        "status": true,
        "msg": "Success",
        "data": [
            {
                "id": 1,
                "email_template_id": "d-ad34ghAwe3mQRvb29",
                "name": "user-verified",
                "description": "Notification to inform a user that they have been verified on the platform",
                "title": "You have been verified",
                "body": "Hello {name}. This is to let you know that your account with email {email} has been verified",
                "type": "user",
                "active": true,
                "notification_channels": [
                    {
                        "id": 1,
                        "type": "EMAIL"
                    }
                ]
            }
        ]
    }
   ```
   - When user is not authenticated, it returns the following `401` response:
   ```json
   {
        "status": false,
        "msg": "You are not authorized to access this API"
   }
   ```

### Exceptions
The following exceptions can be thrown by the package for the scenarios outlined below:
1. `MessengerEntityNotFoundException` `code: 404`
   - Dispatching a Messenger event with a `NotificationData` object that has a `contextName` which does not exist on the database.
2. `MessengerInvalidDataException` `code: 422`
   - Dispatching a Messenger event with a `NotificationData` object without a `contextName` or `recipient`.
   - Attempting to send an Email Notification using a `NotificationContext` that has no `email_template_id`.
   - Attempting to send a Database Notification using a `NotificationContext` that has no `title` or `body`.
   - Attempting to send an Email Notification to a non-notifiable.
   - Attempting to send an Email Notification to a notifiable that has no `email` field or a `routeNotificationForMail()` 
     method in the model class.

## ⛏️ Built Using <a name = "built_using"></a>
- [PHP](https://www.php.net/) - Language
- [Orchestral Testbench](https://github.com/orchestral/testbench) - Library
- [Sendgrid PHP Library](https://github.com/sendgrid/sendgrid-php) - Library

## 📝 TODO <a name = "todo"></a>
- Add support for SMS notifications

## ✍️ Authors <a name = "authors"></a>
- [@chijioke-ibekwe](https://github.com/chijioke-ibekwe) - Initial work
