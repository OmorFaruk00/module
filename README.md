1. composer dump-autoload
2. php artisan migrate
3. php artisan test Modules/Student/Tests


php artisan module:crud Student --fields="name:string,email:email,phone:string,department_id:integer,is_active:boolean"
# type/modifier change (puro definition dite hobe)
php artisan module:crud Student --edit-fields="phone:string:nullable"

# default value
php artisan module:crud Student --edit-fields="status:string:default=active"

# rename + type change
php artisan module:crud Student --edit-fields="phone>mobile:string:nullable"

# multiple
php artisan module:crud Student --edit-fields="name:string,age:integer:nullable"

# nullable field
php artisan module:crud Student --add-fields="age:integer:nullable"

# default value shoho
php artisan module:crud Student --add-fields="status:string:default=active"

# multiple field
php artisan module:crud Student --add-fields="age:integer:nullable,address:text:nullable,dob:date:nullable"

# add + seed
php artisan module:crud Student --add-fields="age:integer:nullable" --seed

php artisan test Modules/Student/Tests
php artisan test Modules/Student/Tests/Feature
php artisan test Modules/Student/Tests/Unit
