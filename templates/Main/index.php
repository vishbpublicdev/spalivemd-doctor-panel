<?php
// echo $this->element('menu');exit;
// debug($menu);exit;
?>

<script type="text/javascript">
    Ext.application({
        name: 'Azure',
        launch: function () {
            App.env_url = "<?= env('url_api', 'https://app.spalivemd.com/api/') ?>";
            App.IS_MASTER = "<?= USER_ID == 1 ? true : false ?>";
            Ext.create('Ext.container.Viewport', {
                layout: 'border', border: false,
                items: [
                    {
                        xtype: 'toolbar', region: 'north', border: false, height: 60,
                        items:[
                            { xtype: 'container', cls: 'logo', height: 40, html: '<img style="margin: -50px 0px 0 0px;" height="140" src="./img/MySpaLive-logo.png">' },'->',
                            { xtype: 'button', text: 'Change password', handler: function(){
                                Ext.create('Wnd.ChangePassword',{
                                    app: {
                                        
                                        callback: Ext.bind(function(){ 
                                            Ext.Msg.alert('Change password',  'Password updated.');
                                        }, this),
                                    }
                                }).show();
                            }, scope: this },
                            { xtype: 'button', text: 'Log Out', handler: function(){App.logout()}, scope: this },
                            
                        ]
                    },
                    App.panelMenu = Ext.create('Ext.panel.Panel', {
                        border: false, region: 'west',bodyCls: 'menu-content', width: 200,
                        layout: {
                            type: 'vbox',       // Arrange child items vertically
                            align: 'stretch',    // Each takes up full width
                            padding: 5
                        },
                        items: [
                            {xtype: 'container', height:80, column: 1},
                            {xtype:'button', text: 'Treatments', handler: function(){ App.open_module('Admin.Tab.Treatments',{}); }},
                            {xtype:'button', text: 'Quality Assurance', handler: function(){ App.open_module('Admin.Tab.Quality',{}); }},
                            {xtype:'button', text: 'Payments', handler: function(){ App.open_module('Admin.Tab.Payments',{}); }},
                            {xtype:'button', text: 'Weight Loss', handler: function(){ App.open_module('Admin.Tab.WeightLoss',{}); }},
                            {xtype:'button', text: 'Settings', handler: function(){ App.open_module('Admin.Tab.Settings',{}); }},
                            //{xtype:'button', text: 'Mint Treatments', handler: function(){ App.open_module('Admin.Tab.MintTreatments',{}); }},

                        ]
                    }),
                    App.tabpanel = Ext.create('Ext.tab.Panel', {
                        border: false, region: 'center', activeTab: 0, bodyCls: 'tab-content',
                        items: []
                    })
                ]
            });

            Ext.onReady(function(){
                //Ext.create('Admin.Tab.Menu',{}).open_module({});
                // App.open_module('Admin.Tab.Treatments',{});
                // App.open_module('Admin.Tab.Embarques',{});
                // App.open_module('Admin.Tab.Catalogos',{});
                // App.open_module('Admin.Tab.Menu',{});
                // App.open_module('Admin.Tab.Roles',{});
                // App.open_module('Admin.Temas',{});
                // App.open_module('Admin.Archivos',{});
                // App.open_module('Admin.Imagenes',{});
                // App.open_module('Admin.Pagina',{});
                // App.open_module('Admin.Paginas',{});
                // Ext.getCmp('tabEmbarques').node_new();
                // Ext.create('Wnd.Usuario',{
                //     title: '*Nuevo Modulo',
                //     app: {
                //         record: {
                //             data: {
                //                 uid: '5e5c9e53a2cf81.29219019'
                //             }
                //         }
                //         // Modulo_Uid: '',
                //         // callback: Ext.bind(function(){ this.reload(); }, this),
                //     }
                // }).show();
            });
        }
    });
</script>