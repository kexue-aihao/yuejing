<?php
return array_replace_recursive(require __DIR__.'/../en/validation.php', ['required' => 'Το πεδίο :attribute είναι υποχρεωτικό.', 'email' => 'Το :attribute πρέπει να είναι έγκυρο email.', 'confirmed' => 'Η επιβεβαίωση του :attribute δεν ταιριάζει.', 'attributes' => ['email' => 'email', 'password' => 'κωδικός', 'name' => 'όνομα']]);
