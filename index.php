<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db_host = 'localhost';
$db_user = 'u82194';
$db_pass = '8381502';
$db_name = 'u82194';

$form_data = [];
$form_errors = [];

if (isset($_COOKIE['form_data'])) {
    $form_data = json_decode($_COOKIE['form_data'], true);
    if (!is_array($form_data)) {
        $form_data = [];
    }
}

if (isset($_COOKIE['form_errors'])) {
    $form_errors = json_decode($_COOKIE['form_errors'], true);
    if (!is_array($form_errors)) {
        $form_errors = [];
    }
    setcookie('form_errors', '', time() - 3600, '/');
}

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $full_name = isset($_POST['fio']) ? trim($_POST['fio']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $birth_date = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $selected_languages = isset($_POST['language']) ? $_POST['language'] : [];
    $biography = isset($_POST['bio']) ? trim($_POST['bio']) : '';
    $contract_accepted = isset($_POST['agreement']) ? true : false;
    
    if (empty($full_name)) {
        $errors['fio'] = 'ФИО обязательно для заполнения';
    } elseif (strlen($full_name) > 150) {
        $errors['fio'] = 'ФИО не может быть длиннее 150 символов';
    } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $full_name)) {
        $errors['fio'] = 'ФИО может содержать только буквы, пробелы и дефисы. Допустимы русские и английские буквы.';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Телефон обязателен для заполнения';
    } elseif (!preg_match('/^(\+7|8)\d{10}$/', $phone)) {
        $errors['phone'] = 'Телефон должен быть в формате +7XXXXXXXXXX или 8XXXXXXXXXX (10 цифр после кода). Допустимы только цифры.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email обязателен для заполнения';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email. Пример: name@domain.com';
    }
    
    if (empty($birth_date)) {
        $errors['birthdate'] = 'Дата рождения обязательна для заполнения';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors['birthdate'] = 'Неверный формат даты. Используйте формат ГГГГ-ММ-ДД';
    } else {
        $date_parts = explode('-', $birth_date);
        if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
            $errors['birthdate'] = 'Указана несуществующая дата';
        }
    }
    
    $allowed_genders = ['male', 'female'];
    if (empty($gender)) {
        $errors['gender'] = 'Пожалуйста, выберите ваш пол';
    } elseif (!in_array($gender, $allowed_genders)) {
        $errors['gender'] = 'Выбрано некорректное значение пола';
    }
    
    if (empty($selected_languages)) {
        $errors['language'] = 'Пожалуйста, выберите хотя бы один язык программирования';
    }
    
    if (!empty($biography)) {
        if (strlen($biography) > 5000) {
            $errors['bio'] = 'Биография не может быть длиннее 5000 символов';
        } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z0-9\s\.,!?\-@#№$%^&*()+=<>:;"\'{}[\]|\\\/]+$/u', $biography)) {
            $errors['bio'] = 'Биография содержит недопустимые символы. Допустимы: буквы, цифры, пробелы, знаки препинания и основные спецсимволы.';
        }
    }
    
    if (!$contract_accepted) {
        $errors['agreement'] = 'Необходимо подтвердить, что вы ознакомлены с контрактом';
    }
    
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted) 
                    VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':full_name' => $full_name,
                ':phone' => $phone,
                ':email' => $email,
                ':birth_date' => $birth_date,
                ':gender' => $gender,
                ':biography' => $biography,
                ':contract_accepted' => $contract_accepted ? 1 : 0
            ]);
            
            $application_id = $pdo->lastInsertId();
            
            foreach ($selected_languages as $lang_name) {
                $stmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = ?");
                $stmt->execute([$lang_name]);
                $lang_id = $stmt->fetchColumn();
                
                if ($lang_id) {
                    $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                    $stmt->execute([$application_id, $lang_id]);
                }
            }
            
            $pdo->commit();
            
            $success_message = 'Данные успешно сохранены!';
            
            $success_data = [
                'fio' => $full_name,
                'phone' => $phone,
                'email' => $email,
                'birthdate' => $birth_date,
                'gender' => $gender,
                'language' => $selected_languages,
                'bio' => $biography,
                'agreement' => $contract_accepted
            ];
            setcookie('form_data', json_encode($success_data), time() + 365 * 24 * 3600, '/');
            
            $form_data = $success_data;
            
        } catch (PDOException $e) {
            if ($pdo) {
                $pdo->rollBack();
            }
            $errors['database'] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    } else {
        setcookie('form_errors', json_encode($errors), 0, '/');
        setcookie('form_data', json_encode($_POST), 0, '/');
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

if (empty($_POST) && !empty($form_data) && empty($errors) && empty($form_errors)) {
    $display_data = $form_data;
} else {
    $display_data = $form_data;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма с валидацией и Cookies</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error-message {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
            margin-bottom: 5px;
            padding: 5px;
            background-color: #f8d7da;
            border-radius: 4px;
        }
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .field-error {
            border-color: #dc3545 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Форма</h2>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['database'])): ?>
            <div class="error-message">
                <?= htmlspecialchars($errors['database']) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="fio">ФИО:</label>
                <input type="text" id="fio" name="fio" 
                       value="<?= isset($display_data['fio']) ? htmlspecialchars($display_data['fio']) : '' ?>"
                       class="<?= isset($form_errors['fio']) || isset($errors['fio']) ? 'field-error' : '' ?>">
                <?php if (isset($form_errors['fio'])): ?>
                    <div class="error-message"><?= $form_errors['fio'] ?></div>
                <?php elseif (isset($errors['fio'])): ?>
                    <div class="error-message"><?= $errors['fio'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" 
                       value="<?= isset($display_data['phone']) ? htmlspecialchars($display_data['phone']) : '' ?>"
                       class="<?= isset($form_errors['phone']) || isset($errors['phone']) ? 'field-error' : '' ?>">
                <?php if (isset($form_errors['phone'])): ?>
                    <div class="error-message"><?= $form_errors['phone'] ?></div>
                <?php elseif (isset($errors['phone'])): ?>
                    <div class="error-message"><?= $errors['phone'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" 
                       value="<?= isset($display_data['email']) ? htmlspecialchars($display_data['email']) : '' ?>"
                       class="<?= isset($form_errors['email']) || isset($errors['email']) ? 'field-error' : '' ?>">
                <?php if (isset($form_errors['email'])): ?>
                    <div class="error-message"><?= $form_errors['email'] ?></div>
                <?php elseif (isset($errors['email'])): ?>
                    <div class="error-message"><?= $errors['email'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="birthdate">Дата рождения:</label>
                <input type="date" id="birthdate" name="birthdate" 
                       value="<?= isset($display_data['birthdate']) ? htmlspecialchars($display_data['birthdate']) : '' ?>"
                       class="<?= isset($form_errors['birthdate']) || isset($errors['birthdate']) ? 'field-error' : '' ?>">
                <?php if (isset($form_errors['birthdate'])): ?>
                    <div class="error-message"><?= $form_errors['birthdate'] ?></div>
                <?php elseif (isset($errors['birthdate'])): ?>
                    <div class="error-message"><?= $errors['birthdate'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Пол:</label>
                <div class="radio-group">
                    <input type="radio" id="male" name="gender" value="male"
                           <?= (isset($display_data['gender']) && $display_data['gender'] == 'male') ? 'checked' : '' ?>
                           class="<?= isset($form_errors['gender']) || isset($errors['gender']) ? 'field-error' : '' ?>">
                    <label for="male">Мужской</label>
                    
                    <input type="radio" id="female" name="gender" value="female"
                           <?= (isset($display_data['gender']) && $display_data['gender'] == 'female') ? 'checked' : '' ?>
                           class="<?= isset($form_errors['gender']) || isset($errors['gender']) ? 'field-error' : '' ?>">
                    <label for="female">Женский</label>
                </div>
                <?php if (isset($form_errors['gender'])): ?>
                    <div class="error-message"><?= $form_errors['gender'] ?></div>
                <?php elseif (isset($errors['gender'])): ?>
                    <div class="error-message"><?= $errors['gender'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="language">Любимый язык программирования:</label>
                <select id="language" name="language[]" multiple size="5"
                        class="<?= isset($form_errors['language']) || isset($errors['language']) ? 'field-error' : '' ?>">
                    <?php
                    $languages_list = [
                        'pascal' => 'Pascal',
                        'c' => 'C',
                        'cpp' => 'C++',
                        'javascript' => 'JavaScript',
                        'php' => 'PHP',
                        'python' => 'Python',
                        'java' => 'Java',
                        'haskell' => 'Haskell',
                        'clojure' => 'Clojure',
                        'prolog' => 'Prolog',
                        'scala' => 'Scala'
                    ];
                    $selected_langs = isset($display_data['language']) ? (array)$display_data['language'] : [];
                    foreach ($languages_list as $value => $label):
                        $selected = in_array($value, $selected_langs) ? 'selected' : '';
                    ?>
                        <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($form_errors['language'])): ?>
                    <div class="error-message"><?= $form_errors['language'] ?></div>
                <?php elseif (isset($errors['language'])): ?>
                    <div class="error-message"><?= $errors['language'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="bio">Биография:</label>
                <textarea id="bio" name="bio" rows="5"
                          class="<?= isset($form_errors['bio']) || isset($errors['bio']) ? 'field-error' : '' ?>"><?= isset($display_data['bio']) ? htmlspecialchars($display_data['bio']) : '' ?></textarea>
                <?php if (isset($form_errors['bio'])): ?>
                    <div class="error-message"><?= $form_errors['bio'] ?></div>
                <?php elseif (isset($errors['bio'])): ?>
                    <div class="error-message"><?= $errors['bio'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group checkbox-wrapper">
                <input type="checkbox" id="agreement" name="agreement" value="1"
                       <?= isset($display_data['agreement']) && $display_data['agreement'] ? 'checked' : '' ?>
                       class="<?= isset($form_errors['agreement']) || isset($errors['agreement']) ? 'field-error' : '' ?>">
                <label for="agreement">С контрактом ознакомлен(а)</label>
                <?php if (isset($form_errors['agreement'])): ?>
                    <div class="error-message"><?= $form_errors['agreement'] ?></div>
                <?php elseif (isset($errors['agreement'])): ?>
                    <div class="error-message"><?= $errors['agreement'] ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</body>
</html>