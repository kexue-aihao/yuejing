<?php
return array_replace_recursive(require __DIR__.'/../en/validation.php', ['required' => ':attribute ያስፈልጋል።', 'email' => ':attribute ትክክለኛ ኢሜይል መሆን አለበት።', 'confirmed' => ':attribute ማረጋገጫ አይዛመድም።', 'attributes' => ['email' => 'ኢሜይል', 'password' => 'የይለፍ ቃል', 'name' => 'ስም']]);
