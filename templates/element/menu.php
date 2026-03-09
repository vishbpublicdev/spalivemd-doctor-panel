<?php
if($allow_restart === true){
    // $data[count($data)-1]['options'][] = '-';
    // $data[count($data)-1]['options'][] = [
    //     'title' => 'Vaciar Tablas',
    //     'icon' => '',
    //     'options' => [],
    //     'level' => 1,
    //     'controller' => '',
    //     'script' => 'App._();',
    // ];
}
?>

<?php foreach ($data as $menu): ?>
    <?php if(is_array($menu)): ?>
    {
        text: '<?= $menu['title'] ?>',
        iconCls: '<?= $menu['icon'] ?>',
        <?php if (!empty($menu['options'])): ?>
        menu: { xtype: 'menu', plain: <?= $menu['level'] == 0? 'true' : 'false' ?>, items: [
            <?= $this->element('menu', ['data' => $menu['options'], 'allow_restart' => false]) ?>
        ]},
        <?php elseif(!empty($menu['controller'])): ?>
        handler: function(button, e){
            App.open_module('<?= $menu['controller'] ?>',{});
        },
        <?php elseif(!empty($menu['script'])): ?>
        handler: function(button, e){
            <?= $menu['script'] ?>
        },
        <?php endif; ?>
    },
    <?php else: ?>
    '<?= $menu ?>',
    <?php endif; ?>
<?php endforeach; ?>