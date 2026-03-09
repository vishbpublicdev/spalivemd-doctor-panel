Ext.define('Admin.Tab.Payments', {
	extend: 'Ext.panel.Panel',
	name: 'Admin.Tab.Payments',
	id: 'tabSpalivePayments',
	title: 'Payments',
	closable: true,
	layout: 'border',
	bodyBorder: false,
	constructor: function(config){
		config.items = [];

		config.items.push(this.__src_grid());

		this.callParent(arguments);
	},afterRender: function() {
		this.callParent(arguments);

		this.on_search();
	},__reload : function() {
		// this.grid.reload();
	},
	__src_grid : function(){
		var _this = this;

		this.grid_doctors = Ext.create('Ux.Grid', {
			cls: 'doctorsGrid',
			tbar: [
				// '->',
				// {xtype: 'textfield', name: 'Preregister_Email', emptyText: 'Search by email', width: 200},
				// '-',
				// { xtype: 'combobox', name: 'Preregister_Status', store: store_filter, width: 220, queryMode: 'local', displayField: 'name', valueField: 'id', allowBlank: false , editable: false, value: 'ALL' },
				// { xtype: 'combobox', name: 'Preregister_Type', store: store_types, width: 220, queryMode: 'local', displayField: 'name', valueField: 'id', allowBlank: false , editable: false, value: 'ALL' },
				// {xtype: 'button', iconCls: 'x-fa fa-searc h', handler: this.on_search, scope: this},
				// {xtype: 'button', iconCls: 'x-fa fa-times', handler: this.on_clear_search, scope: this}
			],
			title: null, pageSize: 20, autoload: true, border: false, url : App.url('paymentsmd'), params: { action: 'grid_doctors_payments' }, 
			remoteGroup:true, remoteSort: true, //groupDir: 'ASC',
			groupField : 'Treatment_YearMonth' , features: [{id: 'group', ftype: 'grouping', groupHeaderTpl: new Ext.XTemplate('<tpl for=".">', '<b>{name}</b>', '</tpl>'), hideGroupedHeader: true, enableGroupingMenu: false}],
			width:'100%',region: 'center',
			columns: [
				// { text: 'Uid', dataIndex: 'Preregister_Uid', sortable: false, width: 200, type: 'string', hidden: true },
				{ text: 'Doctor', dataIndex: 'Treatment_Doctor', sortable: false, flex: 1, type: 'string', cell: {encodeHtml: false},
					renderer: function(val) {
						return val == 'TOTAL' ? '<p style="font-size: 15px; font-weight: bold;text-align:right;">' + val + '</p>' : '<p style="text-align: center;">' + val + '</p>';
					}
				},
				{ text: 'Type', dataIndex: 'Doctor_TotalTreat', sortable: false, flex: 1, type: 'string', cell: {encodeHtml: false},
					renderer: function(val, metadata, record) {
						return record.get('Treatment_Doctor') == 'TOTAL' ? '<p style="font-size: 15px; font-weight: bold; text-align: center;">' + val + '</p>' : '<p style="text-align: center;">' + val + '</p>';
					}
				},
				{ text: 'Total amount', dataIndex: 'Doctor_TotalAmount', sortable: false, flex: 1, type: 'string', cell: {encodeHtml: false},
					renderer: function(val, metadata, record) {
						const NumberFormatter = (value, decimal) => {
							return parseFloat(parseFloat(value).toFixed(decimal)).toLocaleString(
							  "en-IN",
							  {
								useGrouping: true,
							  }
							);
						};


						return record.get('Treatment_Doctor') == 'TOTAL' ? '<p style="text-align:center; font-weight: bold; font-size: 15px;">$' + NumberFormatter(val,2) + '</p>' : '<p style="text-align:center; font-weight: bold;">$' + NumberFormatter(val,2) + '</p>';
					}
				},
				/*{
                    xtype: 'actioncolumn', width: 40, sortable: false,
                    items: [
                        { iconCls: 'x-fa fa-search', cls: 'btnDetail', tooltip: 'See detail', text: 'detail', scope: this, handler: this.on_detail,
							isDisabled: function(view, rowIndex, colIndex, item, record) {
								if (record.get('Treatment_Doctor') == 'TOTAL') return true;

								return false;
							}
						},
                    ]
                }*/
			]
		});

		return this.form_panel = Ext.create('Ext.form.Panel', {
			layout: 'border', region: 'center', //title: 'Filter',
			items: [
				this.grid_doctors,
			],
			buttons: [
				// { text: 'Search', handler: function(){ this.on_search(); },scope: this},
			]
		});

	},reload: function(){
		this.grid.reload();
	},on_search : function(){
	}, on_detail: function(grid, rowIndex, colIndex, item, e, record, row) {
	}
});

Ext.define('Wnd.ChangePassword', {
    extend: 'Ext.window.Window',
    title: 'Change password', modal: true, border: false, width: 400, height: 400, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [], grupos: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());

        config.buttons = [
            ,{text: 'Save', handler: function(){ this.save(); },scope: this}
            ,{text: 'Cancel', handler: function(){ this.close(); },scope: this}
        ];

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);

        this.load();
    },get_form: function(){
        return this.form_panel = Ext.create('Ext.form.Panel',{
            border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' },
            items:[
                {
                    xtype: 'fieldset', title: 'Details', layout:'column', defaults: { margin: '0 0 10 0', labelAlign: 'top', labelWidth:80 },
                    items: [
                        { xtype: 'textfield', fieldLabel: 'Actual Password', name: 'password', inputType: 'password', columnWidth: 1, allowBlank: false, },
                        { xtype: 'textfield', fieldLabel: 'New password', name: 'password_new', inputType: 'password', columnWidth: 1, allowBlank: false, },
                        { xtype: 'textfield', fieldLabel: 'Confirm new password', name: 'password_confirm', inputType: 'password', columnWidth: 1, allowBlank: false, },
                    ]
                },
                { xtype: 'hidden', name: 'uid', value: '' }
            ]
        });
    },load: function(){
        if(!this.app.record) return;

        this.down('hidden[name=uid]').setValue(this.app.record.data.uid);
        this.down('textfield[name=username]').setValue(this.app.record.data.username);
        this.down('textfield[name=password]').setValue(this.generate_password());
    },generate_password: function(){
        var length = 8,
            charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
            retVal = "";
        for (var i = 0, n = charset.length; i < length; ++i) {
            retVal += charset.charAt(Math.floor(Math.random() * n));
        }
        return retVal;
    },save: function(){


        var form = this.form_panel.getForm();
        if (form.isValid()){
            this.el.mask('Wait...');
            form.submit({
                url: App.url('paymentsmd'),
                params: { action: 'password' },
                success: function(form, action){
                    this.el.unmask();
                    this.app.callback();

                    if(action.result.message)
                        Ext.Message.show('Alert', action.result.message);
                    this.close();
                },
                failure: function(form, action){
                    this.el.unmask();
                    // if(action.result.message) Ext.Msg.alert('Aviso', action.result.message);
                },scope: this
            });
        }else{
            Ext.Msg.alert('Alert', 'Please fill all fields.');
        }
    }
});