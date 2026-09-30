1. composer dump-autoload
2. php artisan migrate
3. php artisan test Modules/Student/Tests


php artisan module:crud Student --fields="name:string,email:email,phone:string,department_id:integer,is_active:boolean"
