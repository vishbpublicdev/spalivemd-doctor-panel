<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MySpaLive Panel</title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css('theme-triton-all') ?>
    <?= $this->Html->css('override') ?>
    <?= $this->Html->css('style') ?>

    <?= $this->Html->script('ext-all') ?>
    <!-- <?= $this->Html->script('locale-es') ?> -->
    <?= $this->Html->script('ux-grid') ?>
    <?= $this->Html->script('ux-combobox') ?>
    <?= $this->Html->script('ux-dataview') ?>
    <?= $this->Html->script('override') ?>

    <meta name="csrf_token" content="<?= $this->getRequest()->getAttribute('csrfToken') ?>">
</head>
<body>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBkDaVFDigisPTOruuE-Z_oHUD6w7W2Rjo&libraries=&v=weekly"
     async></script>
     <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" integrity="sha512-qTXRIMyZIFb8iQcfjXWCO8+M5Tbc38Qi5WzdPOYZHIlZpzBHG3L3by84BBBOiRGiEb7KKtAOAs5qYdUiZiQNNQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <?= $this->Html->script('app') ?>
    <?= $this->Html->script('/modules') ?>

    <?= $this->Flash->render() ?>
    <?= $this->fetch('content') ?>
</body>
</html>