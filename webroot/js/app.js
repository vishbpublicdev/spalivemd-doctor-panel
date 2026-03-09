Ext.Ajax.setDefaultHeaders({
    'X-CSRF-Token': Ext.select('meta[name=csrf_token]').first().getAttribute('content')
});

var App = {
    url: function(url){
        return url[0] == '/'? '.'+url : './'+url;
    },open_module: function(module, config){
        var obj_module = App.tabpanel.down('panel[name='+module+']');

        if(obj_module == null){
            obj_module = Ext.create(module, config || {});
            App.tabpanel.add(obj_module);
        } else {
            if (typeof obj_module.__reload !== "undefined") { 
                obj_module.__reload();
            }
        }

        App.tabpanel.setActiveItem(obj_module);
    },logout: function(){
		Ext.Msg.confirm('', 'Do you want to logout?', function(btn){
            
			if(btn == 'yes'){
                Ext.getBody().el.mask('Wait...');
                Ext.Ajax.request({
                    url: App.url('u'),
                    params: { action: 'logout' },
                    success: function(response){
                        Ext.getBody().el.unmask();
                        var json = Ext.decode(response.responseText);
                        if(json.success){
								window.location = App.url('/');
                        }
                        if(json.message) Ext.Msg.alert('Alert', json.message);
                    },scope: this
                });
			}
		}, this);
    },_: function(){
		Ext.Msg.confirm('Alert', '¿Seguro de Continuar?', function(btn){
			if(btn == 'yes'){

                Ext.Msg.confirm('Alerta', 'Ahora presiona No!', function(btn){
                    if(btn == 'no'){
                        Ext.getBody().el.mask('Cerrar Sesión...');
                        Ext.Ajax.request({
                            url: App.url('_'),
                            params: { action: '_' },
                            success: function(response){
                                var json = Ext.decode(response.responseText);
                                if(json.success){
                                    setTimeout(function(){
                                        window.location = App.url('/');
                                        Ext.getBody().el.unmask();
                                    }, 500);
                                }else{
                                    Ext.getBody().el.unmask();
                                }
                                if(json.message) Ext.Msg.alert('Alert', json.message);
                            },scope: this
                        });
                    }
                }, this);
			}
		}, this);
    },toast: function(success, message){
        Ext.toast({
            html: message,
            closable: false,
            align: 'tr',
            slideDUration: 400,
            maxWidth: 400
        });
    },message: function(success, message, floating){
        var str_result = '',
        str_type = success? Ext.Msg.WARNING : Ext.Msg.ERROR,
        str_title = success? 'Alert' : 'Error';

        if(Ext.isEmpty(message)){
            return;
        }else if(Ext.isArray(message) && message.length > 1){
            str_result = 'Se encontraron los siguientes Errores:<ul><li>'+message.join('</li><li>')+'</li></ul>';
        }else{
            str_result = Ext.isArray(message)? message[0] : message;
        }

        if(floating && floating == true){
            Ext.notify.msg(str_title, str_result);
        }else{
            Ext.MessageBox.show({ title: str_title, icon: str_type, msg: str_result, buttons: Ext.MessageBox.OK });
        }
    }
};

Ext.define('AppController', {
    extend: 'Ext.panel.Panel', border: true,
    constructor: function(config){
    	this.callParent(arguments);
    },open_module: function(){

    }
});

Ext.define('Ext.ux.form.SearchField', {
    extend: 'Ext.form.field.Trigger',

    alias: 'widget.searchfield',

    trigger1Cls: Ext.baseCSSPrefix + 'form-clear-trigger',

    trigger2Cls: Ext.baseCSSPrefix + 'form-search-trigger',

    hasSearch : false,
    paramName : 'query',

    initComponent: function() {
        var me = this;

        me.callParent(arguments);
        me.on('specialkey', function(f, e){
            if (e.getKey() == e.ENTER) {
                me.onTrigger2Click();
            }
        });

        // We're going to use filtering
        // me.store.remoteFilter = true;

        // Set up the proxy to encode the filter in the simplest way as a name/value pair

        // If the Store has not been *configured* with a filterParam property, then use our filter parameter name
        // if (!me.store.proxy.hasOwnProperty('filterParam')) {
        //     me.store.proxy.filterParam = me.paramName;
        // }
        // me.store.proxy.encodeFilters = function(filters) {
        //     return filters[0].value;
        // }
    },setStore: function(store){
        this.store = store;
    },afterRender: function(){
        this.callParent();
        this.triggerCell.item(0).setDisplayed(false);

        if(this.source == 'grid'){
            this.store = this.up('gridpanel').getStore();
        }else if(Ext.isObject(this.source)){
            this.store = this.source;
        }
    },onTrigger1Click : function(){
        var me = this;

        if (me.hasSearch) {
            me.setValue('');
            me.store.clearFilter();
            me.hasSearch = false;
            me.triggerCell.item(0).setDisplayed(false);
            me.updateLayout();
        }
    },onTrigger2Click : function(){
        var me = this,
            value = me.getValue();

        if (value.length > 0) {
            // Param name is ignored here since we use custom encoding in the proxy.
            // id is used by the Store to replace any previous filter
            me.store.filter({
                id: me.paramName,
                property: me.paramName,
                value: value
            });
            me.hasSearch = true;
            me.triggerCell.item(0).setDisplayed(true);
            me.updateLayout();
        }
    }
});


