<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MySpaLive Panel</title>
	<base href="<?= $this->Url->build('/', ['fullBase' => true]) ?>" />
    <meta name="csrf_token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
    <script src="https://www.google.com/recaptcha/api.js"></script>
    
    <?= $this->Html->css('login') ?>

    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->script('jquery.min') ?>
</head>
<body>
    <?= $this->Flash->render() ?>
    <?= $this->fetch('content') ?>
</body>
</html>