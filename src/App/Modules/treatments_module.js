Ext.define('Admin.Tab.Treatments', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Treatments',
    id: 'tabTreatments',
    title: 'Treatments',
    closable: true,
    layout: 'fit',
    bodyBorder: false,
    constructor: function(config){
        config.items = [];

        config.items.push(this.__src_grid());

        this.callParent(arguments);
    },__reload : function() {
        this.grid.reload();
    },
    __src_grid : function(){


        var store_mds = Ext.create('Ext.data.Store', {
            autoLoad: true,
            fields: [
                { name: 'id', type: 'int' },
                { name: 'name', type: 'string' },
            ],proxy: {
                type: 'ajax', url: App.url('treatments'), extraParams: { action: 'listmd' },
                reader: { type: 'json', root: 'data', totalProperty: 'count' }
            }
        });

        var store_filter_cat = Ext.create('Ext.data.Store', {
            fields: ['id', 'name'],
            data : [
                {'id':'ALL', 'name': 'ALL'},
                {'id':'NEUROTOXINS BASIC', 'name': 'NEUROTOXINS BASIC'},
                {'id':'NEUROTOXINS ADVANCED', 'name': 'NEUROTOXINS ADVANCED'},
                {'id':'IV THERAPY', 'name': 'IV THERAPY'},
            ]
        });

        _this = this;
        this.grid = Ext.create('Ux.Grid', {
            // selModel: cboxSelModel,
            selModel: {
                selType: 'checkboxmodel',
                checkOnly: false,
                mode: 'MULTI',
                // multiSelect: true,
                // singleSelect: false
                injectCheckbox: 'last',
                headerText: 'Test',
                showHeaderCheckbox: false
                // listeners: {
                //     select: function (row, record, index, eOpts) {
                //         console.log('aqui');
                //     },
                //     change: function (row, record, index, eOpts) {
                //         console.log('aca');
                //     },
                //     scope: this
                // }
            },
            listeners: {
                select: function (row, record, index, eOpts) {
                    // console.log('index', index);
                    // console.log('record', record.data);
                    // console.log('-------------------');
                    if (record.get('approved') == 'PENDING') return false;

                    row.deselect(record);
                    // App.message(false, "A record is unavailable for authorize payment.");
                    // Ext.toast('This record is unavailable for authorize payment.', 'Alert');
                },
                itemclick: function( view , record , item , index , e , eOpts ) {
                    // this.node_load(null, null, null, item, e, record );
                    // console.log('itemclick', record.data);
                    // console.log('eOpts', this.grid.getSelectionModel().setConfig);
                },
                scope: this
            },
            viewConfig: {
                getRowClass: function(record) {
                    if (record.get('approved') == 'PENDING') return '';

                    return 'selection-disabled';
                },
                enableTextSelection: true,
                stripeRows: false, 
                getRowClass: function(record) {
                    var cls = '';

                    if (record.get('approved') != 'PENDING') cls += ' selection-disabled ';
                    if (record.get('login_status') == 'APPROVE') cls += 'green-row';
                    
                    return cls;
                } 
            },
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Search', source: 'grid' }, '-',
                { xtype: 'combobox', fieldLabel: 'Doctor:', labelWidth: 50, tipo: 'filter', columnWidth: 1, name: 'type', store: store_mds, width: 300, queryMode: 'local', displayField: 'name', valueField: 'id', 
                allowBlank: false , editable: false, value: 0, hidden: !App.IS_MASTER,
                        listeners : {
                            select : function() {
                                _this.grid.store.reload();
                            }
                    }
                },
                { xtype: 'combobox', fieldLabel: 'Cat:', labelWidth: 50, name: 'tr_data_cat', store: store_filter_cat, width: 350, queryMode: 'local', displayField: 'name', valueField: 'id', 
                    allowBlank: false , editable: false, value: '-',
                        listeners : {
                            select: function(combobox, record, eOpts) {
                                _this.grid.store.reload();
                            }
                        }
                    },
                '->',
                { xtype: 'button', text: 'Approve / Reject multiple treatments', handler: this.on_approve_multiple_treatments, scope: this }
            ],
            pageSize: 50, autoload: true, border: false, url : App.url('treatments'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
                // { text: 'No', width: 45, dataIndex: 'id', hidden: false, sortable: false, type: 'string' },
                { text: 'Treatment date', dataIndex: 'schedule_date', sortable: false, flex: 1, type: 'string', renderer: function(date) {return moment(date).format('MM-D-YYYY h:mm A');} },
                { text: 'Patient', dataIndex: 'patient', sortable: false, flex: 1, type: 'string' },
                { text: 'Injector', dataIndex: 'injector', sortable: false, flex: 1, type: 'string' },
                { text: 'Injector\'s phone', dataIndex: 'injector_phone', sortable: false, flex: 1, type: 'string' },
                { text: 'Injector\'s email', dataIndex: 'injector_email', sortable: false, flex: 1, type: 'string' },
                { text: 'Examiners', dataIndex: 'examiners', sortable: false, flex: 1, type: 'string' },
                { text: 'Doctor', dataIndex: 'assigned_doctor', sortable: false, flex: 1, type: 'string', hidden: !App.IS_MASTER },
                 { text: 'Photos', dataIndex: 'photos', sortable: false, width: 100, type: 'string',
                     renderer: function(value, metadata, record) { 
                            if (value == 1) {
                                // metadata.tdCls = 'green-row';
                                return 'YES';
                            } else {
                                // metadata.tdCls = 'yellow-row';
                                return 'NO';
                            }
                            return value;
                        }   
                    },
                    { text: 'Category', dataIndex: 'type_category', sortable: false, flex: 1, type: 'string' },   
                    { text: 'Treatments ', dataIndex: 'treatments', sortable: false, flex: 1, type: 'string' },   
                { text: 'Approve/Reject', dataIndex: 'approved', sortable: false, width: 130, type: 'string',
                    renderer: function(value, metadata, record) { 
                        if (value == 'REJECTED') {
                            metadata.tdCls = 'red-row';
                        } else if (value == 'PENDING') {
                            metadata.tdCls = 'yellow-row';
                        } else if (value == 'APPROVED') {
                            metadata.tdCls = 'green-row';
                        }
                        return value;
                    }
                },
                {
                    xtype: 'actioncolumn', width: 60, sortable: false,
                    items: [
                        { iconCls: 'x-fa fa-edit', tooltip: 'Approve/Reject', scope: this, handler: this.node_edit, isDisabled: function(view, rowIndex, colIndex, item, record){ return record.data.approved != 'PENDING'? true : false; } },
                    ]
                },
                { text: ' ', dataIndex: 'trainings', hidden: true, hideable: false, width: 1, sortable: false, type: 'auto' }
            ],tplRowExpand : new Ext.XTemplate(
               `<p><b>Treatments:</b> {treatments}</p>
                    <p><b>Injector\'s Notes :</b> {notes}</p>
                    <p><b>Doctor\'s Notes :</b> {doctor_notes}</p>
                    <p><b>Associated certificates:</b> 
                        <tpl for="certificates">
                            <a class="cert-link" target="blank" href="{[this.get_certificate_url(values)]}">Certificate</a>
                        </tpl>
                    </p>
                    <p><b>Injector Certifications:</b>
                        <tpl for="trainings">
                            <a class="gfe-link" target="blank" href="{[this.get_training_certificate_url(values)]}">{show}</a><br/>
                        </tpl>
                    </p>
                    <p><tpl for="files">
                            <a target="blank" href="{[this.get_img_url(values)]}"><img style=" margin: 10px;" height="120" width="120" src="{[this.get_img_url(values)]}" /></a>
                        </tpl>
                    </p>
                    <p><b>Agreements:</b> 
                        <tpl for="agrement">
                            <a class="gfe-link" target="blank" href="{[this.get_agreement_url(values)]}">{[this.get_agreement_type(values)]}</a>
                        </tpl>
                    </p>
                    `,
                    {
                        get_url: function(values){
                            var url = App.env_url + '?key=5hf3gdhgfi3ugjbni3ifgisdfgn45h.sdfg3hhuh&action=get-certificate&l3n4p=6092482f7ce858.91169218&uid=' + values.uid
                            return url;
                        },
                        get_gfe_url: function(values){
                            var url = App.env_url + '?key=5hf3gdhgfi3ugjbni3ifgisdfgn45h.sdfg3hhuh&action=get-gfe&l3n4p=6092482f7ce858.91169218&uid=' + values.uid
                            return url;
                        },
                        get_img_url: function(values){
                            var url = App.env_url + '?key=5hf3gdhgfi3ugjbni3ifgisdfgn45h.sdfg3hhuh&action=get-img&l3n4p=6092482f7ce858.91169218&id=' + values
                            return url;
                        },
                        get_agreement_url: function(values){
                            var url = App.env_url + '?key=225sadfgasd123fgkhijjdsadfg16578g12gg3gh&action=print_agreement&l3n4p=6092482f7ce858.91169218&uid='+values.uid+'&patient_uid=' + values.patient_uid
                            return url;
                        },
                        get_agreement_type: function(values){
                            var url = values.agreement_title
                            return url;
                        },
                        get_certificate_url: function(values){                                                        
                            return values.certificate_url;                                                                             
                        },
                        get_training_certificate_url: function(values) {
                            var uid = values.id_course;
                            var user_uid = values.user_uid;
                            var os = values.os;
                            if (!uid) return '';
                            if (os == 0) {
                                return App.env_url + '?key=2fe548d5ae881ccfbe2be3f6237d7951&l3n4p=6092482f7ce858.91169218&action=get_training_cert&id=' + uid + '&user_uid=' + user_uid;
                            }
                            return App.env_url + '?key=2fe548d5ae881ccfbe2be3f6237d7951&l3n4p=6092482f7ce858.91169218&action=get_training_cert_os&id=' + uid + '&user_uid=' + user_uid;
                        },
                    }
            ),
        });

        this.grid.store.on( 'beforeload', function( store, options ) {

            var cbc= _this.grid.down('combobox[name=tr_data_cat]');
            var cb = _this.grid.down('combobox');
            if (cb) {
                Ext.apply(store.getProxy().extraParams, {
                       mfilter: cb.getValue(),
                       cat: cbc.getValue(),
                    });
            }
        });

         return this.west_panel = Ext.create('Ext.panel.Panel',{
            layout:'accordion', activeTab: 0, plain: true, border: true, height: '100%', background: 'red',//bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            items: [
                this.grid,
            ]
        });

    },reload: function(callback){
        this.grid.reload(callback);
        
    },node_edit: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.create('Wnd.ApprTreatment', {
            app: {record: record, callback: Ext.bind(function(){ this.reload(); }, this)}
        }).show();
    }, on_approve_multiple_treatments: function() {
        var selectedRecords = this.grid.getSelectionModel().getSelected().items;
        
        if (selectedRecords.length == 0) App.message(false, 'You must select a record(s) to approve.');
        else {
            // Ext.Msg.confirm('Authorize payments', 'Do you want to approve / reject ' + selectedRecords.length + ' treatment(s)?', function(btn){
            //     if(btn == 'yes'){
                    Ext.create('Wnd.ApprTreatment', {
                        title: 'Evaluate ' + selectedRecords.length + ' treatment(s)',
                        app: {multiple: true, selections: selectedRecords, callback: Ext.bind(function(){ this.reload(); }, this)}
                    }).show();
            //     }
            // }, this);
        }
    },
});


Ext.define('Wnd.ApprTreatment', {
    extend: 'Ext.window.Window',
    title: 'Evaluate treatment', modal: true, border: false, width: 600, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [], grupos: [], multiple: false, selections: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form(config.app));

        config.buttons = [
            {text: 'Save', handler: function(){ this.save(); },scope: this},
            {text: 'Cancel', handler: function(){ this.close(); },scope: this}
        ];

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);

        this.load();
    },get_form: function(app){
        var isHidden = !app.record ? false : true;
        this.store_usrtype = Ext.create('Ext.data.Store', {
            fields:['uid','value'],
            data: [{uid: 'APPROVED', value: 'Approve'},{uid: 'REJECTED', value: 'Reject'}]
        });

        var panel_items = [];

        if (this.app.multiple == false) panel_items.push({ xtype: 'dataview', tpl: this._tpl_trat(), columnWidth: 1, allowBlank: false });

        panel_items.push({ xtype: 'textarea', fieldLabel: 'Notes', name: 'notes', columnWidth: 1, allowBlank: true});
        panel_items.push({ xtype: 'combobox', fieldLabel: 'Evaluate treatment', name: 'approved', store: this.store_usrtype, valueField: 'uid', displayField: 'value', columnWidth: 0.5, allowBlank: false, editable: false, forceSelection: true});
        panel_items.push({ xtype: 'hidden', name: 'uid', value: '' });

        return this.form_panel = Ext.create('Ext.form.Panel',{
            region: 'center', border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' }, split: true,
            items:[
                {
                    xtype: 'fieldset', title: 'Details', layout:'column', defaults: { margin: '0 0 10 0', labelAlign: 'top', labelWidth:80 },
                    items: panel_items
                }
            ]
        });
    },load: function(record){
        if(!this.app.record) return;
        console.log('RECORD\n', this.app.record.data);

        this.form_panel.down('dataview').setData(this.app.record.data);
        this.el.mask('Loading...');
        this.form_panel.getForm().load({
            url: App.url('treatments'),
            params: { action: 'load', uid : this.app.record.data.uid },
            success: function(form, action){
                this.el.unmask();
            },
            failure: function(form, action){
                this.el.unmask();
                App.message(false, json.message);
            },scope: this
        });

    },save: function(){
        var form = this.form_panel.getForm();
        if (form.isValid()){
            if (this.app.multiple) {
                this.el.mask('Wait...');
                for (let i = 0; i < this.app.selections.length; i++) {
                    const record = this.app.selections[i];
                            
                    form.submit({
                        url: App.url('treatments'),
                        params: { action: 'save', treatment_uid: record.get('uid')},
                        success: function(form, action){

                            if ((i + 1) == this.app.selections.length) {
                                this.el.unmask();

                                App.message(true, this.app.selections.length + ' treatments approved!');
                                this.app.callback(action.result);

                                // if(action.result.message)
                                //     Ext.Message.show('Warning', action.result.message);
                                this.close();
                            }
                        },
                        failure: function(form, action){
                            this.el.unmask();
                            if(action.result.message) Ext.Msg.alert('Warning', action.result.message);
                        },scope: this
                    });
                }
            } else {
                this.el.mask('Saving...');
                form.submit({
                    url: App.url('treatments'),
                    params: { action: 'save', treatment_uid: this.app.record.data.uid},
                    success: function(form, action){
                        this.el.unmask();
    
                        this.app.callback(action.result);
                        console.log(action.result);
                        if(action.result.message)
                            Ext.Message.show('Warning', action.result.message);
                        this.close();
                    },
                    failure: function(form, action){
                        this.el.unmask();
                        console.log(action.result);
                        if(action.result.message) Ext.Msg.alert('Warning', action.result.message);
                    },scope: this
                });
            }
        }else{
            Ext.Msg.alert('Warning', 'You must fill the required fields.');
        }
    },_tpl_trat: function(){
        return new Ext.XTemplate(
            `
            <tpl for=".">
                <p><b>Treatments:</b> {treatments}</p>
                <p><b>Injector\'s Notes :</b> {notes}</p>
            </tpl>
            `,
            {
                set_files: function(value){
                   return '';
                }
            }
        );
    }
});


