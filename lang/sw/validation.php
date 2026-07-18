<?php
return array_replace_recursive(require __DIR__.'/../en/validation.php', ['required' => 'Sehemu ya :attribute inahitajika.', 'email' => ':attribute lazima iwe barua pepe halali.', 'confirmed' => 'Uthibitishaji wa :attribute haulingani.', 'attributes' => ['email' => 'barua pepe', 'password' => 'nenosiri', 'name' => 'jina']]);
