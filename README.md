# For setting up the project:


    1. Copy .env.example file and rename to .env
    2. Set DB_USERNAME and DB_PASSWORD to your choice in .env
    3. Set QUEUE_CONNECTION to database in .env
    3. Create mysql database and rename it to the value for DB_NAME in .env
    4. Copy .env and rename .env.testing and set DB_NAME to cantrallockingsystemtesting and provide DB_USERNAME and DB_PASSWORD
    3. Run composer install in project directory
    4. Run "php artisan migrate"
    5. Run "php artisan db:seed" for seeding starter resources
    6. Run "php artisan queue:work"
    7. Run "php artisan serve"


# For using the server with a http client (curl for example):


    - Retrieving current resources: curl http://localhost:8000/api/resources

    
    - Acquiring Resource1 for indefinite time: curl -X PATCH -H "Content-Type: application/json" -d '{"action": "acquire", "key":"2222222222"}' http://localhost:8000/api/resources/Resource1

    
    - Acquiring Resource2 for some time: curl -X PATCH -H "Content-Type: application/json" -d '{"action": "acquire", "key":"8888888888", "period": 60}' http://localhost:8000/api/resources/Resource2
    
    
    - Releasing Resource1: curl -X PATCH -H "Content-Type: application/json" -d '{"action": "release", "key":"2222222222"}' http://localhost:8000/api/resources/Resource1


# For running tests:


    - In project directory run "php artisan test"
