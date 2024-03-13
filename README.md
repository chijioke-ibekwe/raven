
# <div align="center">Raven</div>
<div align="center">

[![Status](https://img.shields.io/badge/status-active-success.svg)]()
[![GitHub Issues](https://img.shields.io/github/issues/chijioke-ibekwe/The-Documentation-Compendium.svg)](https://github.com/chijioke-ibekwe/raven/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/chijioke-ibekwe/The-Documentation-Compendium.svg)](https://github.com/chijioke-ibekwe/raven/pulls)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](/LICENSE)

</div>

---

<p align="center"> Multi-channel Laravel notification package
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
In Laravel, crafting notification classes can often feel repetitive (and WET), especially in projects that rely 
heavily on notifications. Meet Raven – the solution that streamlines the process of sending notifications through 
multiple channels in Laravel, allowing you to focus on your peculiar business logic. Currently, Raven seamlessly handles
email notifications through SendGrid and Amazon SES, as well as database notifications. Stay tuned, as support for SMS 
notifications will be integrated in the near future.

## 🏁 Getting Started <a name = "getting_started"></a>

### Prerequisites
To use this package, you need the following requirements:

1. PHP >= v8.0
2. Laravel >= v8.0
3. Composer

## 🎈 Usage <a name="usage"></a>
1. You can install this package via Composer using the command:
   ```bash
    composer require chijioke-ibekwe/raven
    ```

2. Next, you will need to publish and run the migration files, and the config file. The following command will allow you
   do all of the above:
    ```bash
    php artisan raven:install
    ```

3. The migrations will be published in your project's migrations directory `./database/migrations` while the config file
   `raven.php`, will be published in your config directory `./config`. Content of the config file is as shown below:
    ```php
   <?php

    return [
    
        'default' => [
            'email' => env('EMAIL_NOTIFICATION_PROVIDER', 'sendgrid'),
            'sms' => env('SMS_NOTIFICATION_PROVIDER', 'nexmo')
        ],
    
        'providers' => [
            'sendgrid' => [
                'key' => env('SENDGRID_API_KEY')
            ],
            'ses' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'template_source' => env('AWS_SES_TEMPLATE_SOURCE', 'sendgrid'),
                'template_directory' => env('AWS_SES_TEMPLATE_DIRECTORY', 'resources/views/emails')
            ]
        ],
    
        'customizations' => [
            'mail' => [
                'from' => [
                    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                    'name' => env('MAIL_FROM_NAME', 'Example'),
                ]
            ],
            'queue_name' => env('RAVEN_QUEUE_NAME')
        ],
    
        'api' => [
            'prefix' => 'api/v1',
            'middleware' => 'api'
        ]
    
    ];
    ```
   - The `default` array allows you to configure your default service providers for your notification channels. Options
     are `sendgrid` and `ses`. (`nexmo` for SMS will be integrated soon).
   - The `providers` array is where you supply the credentials for the service provider you choose. When using `ses`, you 
     can provide the email template in 2 ways. 
     - First is by hosting your email template on `sendgrid`. If this is your preferred option, the `template_source` should be 
       set as `sendgrid`. NB: For this to work, you need to also provide your credentials for the `sendgrid` provider. 
     - Second option is by storing your email templates on the file system as blade templates. The `template_source` in 
       this case should be set as `file` and the directory of the templates should be provided on the `template_directory`.
       (This option is not currently available, but will be in the near future).
   - The `customizations` array allows you to customize your email parameters, and optionally your `queue_name` (not 
     queue connection) for queueing your notifications. If a queue name is not provided, the default queue will be used.
   - The `api` array allows you to customize the provided API routes.

4. After the migrations have been run successfully, you can then proceed to add notification contexts to the database.
   To do this, simply create and run migration files similar to the ones below:
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

5. To send a notification at any point in your code, build a `Scroll` object, set the relevant 
   fields as shown below, and dispatch a `Raven` with the `Scroll`:

    ```php
            $verified_user = User::find(1);
            $document_url = "https://example.com/laravel-cheatsheet.pdf";

            $scroll = new Scroll();
            $scroll->setContextName('user-verified');
            $scroll->setRecipients([$verified_user, 'admin@raven.com']);
            $scroll->setCcs(['john.doe@raven.com' => 'John Doe', 'jane.doe@raven.com' => 'Jane Doe'])
            $scroll->setParams([
                'id' => $verified_user->id,
                'name' => $verified_user->name
                'email' => $verified_user->email
            ]);
            $scroll->setAttachmentUrls($document_url)
    
            Raven::dispatch($scroll);
    ```
    - The `contextName` property is required and must match the notification context name for that notification 
      on the database.  
    - The `recipients` property is required and takes any single notifiable/email string, or an array of notifiables/email
      strings that should receive the notification.  
    - The `ccs` property is exclusively for email notifications and takes an array (or associative array with email/name as 
      key/value pairs respectively) of emails you want to CC on the email notification.     
    - The `params` property is an associative array of all the variables that exist on the notification 
      template with their values, where the key must match the variable name on the template.  
    - Finally, the `attachmentUrls` field takes a url or an array of urls that point to the publicly accessible resource(s) that 
      needs to be attached to the email notification.  

6. To successfully send Database Notifications, it is assumed that the user of this package has already set up a 
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
        'body' => $body
     ];
    ```
    The `title` and `body` properties are obtained from the notification context for the said notification on the database.

7. The package takes care of the rest of the logic.

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
1. `RavenEntityNotFoundException` `code: 404`
   - Dispatching a Raven with a `Scroll` object that has a `contextName` which does not exist on the database.
2. `RavenInvalidDataException` `code: 422`
   - Dispatching a Raven with a `Scroll` object without a `contextName` or `recipient`.
   - Attempting to send an Email Notification using a `NotificationContext` that has no `email_template_id`.
   - Attempting to send a Database Notification using a `NotificationContext` that has no `title` or `body`.
   - Attempting to send an Email Notification to a notifiable that has no `email` field or a `routeNotificationForMail()` 
     method in the model class.

## ⛏️ Built Using <a name = "built_using"></a>
- [PHP](https://www.php.net/) - Language
- [Orchestral Testbench](https://github.com/orchestral/testbench) - Library
- [AWS PHP SDK](https://github.com/aws/aws-sdk-php) - Library
- [Sendgrid PHP Library](https://github.com/sendgrid/sendgrid-php) - Library
- [PHP Mailer](https://github.com/PHPMailer/PHPMailer) - Library

## 📝 TODO <a name = "todo"></a>
- Add support for SMS notifications

## ✍️ Authors <a name = "authors"></a>
- [@chijioke-ibekwe](https://github.com/chijioke-ibekwe) - Initial work
