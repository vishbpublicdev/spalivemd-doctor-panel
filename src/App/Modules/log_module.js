Ext.define('Admin.Tab.Log', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Log',
    id: 'tabLog',
    title: 'Log',
    closable: true,
    layout: 'border',
    bodyBorder: false,
    constructor: function(config){
        config.items = [];

        config.items.push(this.__src_west());

        this.callParent(arguments);
    },__reload : function() {
        this.grid.reload();
        this.grid2.reload();
    },
    __src_west : function(){
        this.grid = Ext.create('Ux.Grid', {
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Search by action or ip', source: 'grid' }, '-','->',
            ], region: 'center', width: '60%', title: 'Log',
            pageSize: 50, autoload: true, border: false, url : App.url('debug'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
                { text: 'ID', width: 100, dataIndex: 'id', hidden: false, sortable: false, type: 'string' },
                { text: 'Action', dataIndex: 'action', sortable: false, width: 180, type: 'string' },
                { text: 'User', dataIndex: 'user', sortable: false, width: 180, type: 'string' },
                { text: 'APP', dataIndex: 'source', sortable: false, width: 180, type: 'string' },
                { text: 'IP', dataIndex: 'ip', sortable: false, width: 250, type: 'string' },
                
                { text: 'Datetime', dataIndex: 'created', sortable: false, width: 250, type: 'string', renderer: Ext.util.Format.dateRenderer('m-d-Y g:i A') },
               
            ],tplRowExpand : new Ext.XTemplate(
                '<p><b>Agent:</b> {agent}</p>',
                '<p><b>Post:</b> {post}</p>',
                '<p><b>Get:</b> {get}</p>',
                '<p><b>Response:</b> {result}</p>',
                {
                    set_files: function(value){
                       return '';
                    }
                }
            ),
        });

        this.grid2 = Ext.create('Ux.Grid', {
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Search by description', source: 'grid' }, '-','->',
            ], region: 'east', width: '40%', title: 'Bugs',
            pageSize: 50, autoload: true, border: false, url : App.url('debug'), params : { action: 'grid_bugs' }, model:'Modulo', remoteSort: true,
            columns: [
                { text: 'ID', width: 100, dataIndex: 'id', hidden: false, sortable: false, type: 'string' },
                { text: 'Title', dataIndex: 'title', sortable: false, width: 180, type: 'string' },
                { text: 'App', dataIndex: 'op_system', sortable: false, width: 180, type: 'string' },
                { text: 'Datetime', dataIndex: 'created', sortable: false, width: 250, type: 'string', renderer: Ext.util.Format.dateRenderer('m-d-Y g:i A') },
               
            ],tplRowExpand : new Ext.XTemplate(
                '<p><b>OS Version:</b> {ver_system}</p>',
                '<p><b>Description:</b> {description}</p>',
                {
                    set_files: function(value){
                       return '';
                    }
                }
            ),
        });

         return this.west_panel = Ext.create('Ext.panel.Panel',{
            layout:'border', region: 'center', activeTab: 0, plain: true, border: true, height: '100%', background: 'red',//bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            items: [
                this.grid,
                this.grid2,
            ],
            // bbar: [
            //     { xtype: 'label', typo:'lbl_sales', text: 'Sales $0', hidden: true },
            //     { text: 'See pyramid', handler: this.on_pyramid, style: 'background-color:#df779e;', cls: 'injectors-bbar', scope: this },
            //     { text: 'See map', handler: this.on_map, style: 'background-color:#df779e;', cls: 'injectors-bbar', scope: this },
            // ]
        });

    }
});



Ext.define('Wnd.UserLog', {
    extend: 'Ext.window.Window',
    title: 'User Log', modal: true, border: false, width: 800, height: 600, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [], grupos: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_pgrid());

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);

        this.load();
    },get_pgrid: function(){
        return this.pgrid = Ext.create('Ux.Grid', {
            pageSize: 50, autoload: false, border: false, url : App.url('debug'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            bbar: {
                
            },
             columns: [
                { text: 'ID', width: 100, dataIndex: 'id', hidden: false, sortable: false, type: 'string' },
                { text: 'Action', dataIndex: 'action', sortable: false, width: 180, type: 'string' },
                { text: 'User', dataIndex: 'user', sortable: false, width: 180, type: 'string' },
                { text: 'APP', dataIndex: 'source', sortable: false, width: 180, type: 'string' },
                { text: 'IP', dataIndex: 'ip', sortable: false, width: 250, type: 'string' },
                
                { text: 'Datetime', dataIndex: 'created', sortable: false, width: 250, type: 'string', renderer: Ext.util.Format.dateRenderer('m-d-Y g:i A') },
               
            ],tplRowExpand : new Ext.XTemplate(
                '<p><b>Agent:</b> {agent}</p>',
                '<p><b>Post:</b> {post}</p>',
                '<p><b>Get:</b> {get}</p>',
                '<p><b>Response:</b> {result}</p>',
                {
                    set_files: function(value){
                       return '';
                    }
                }
            ),
           
        });
    },load: function(){
        var record = this.app.record;
        var user = record.get('user');
        var _filter = '[{"property":"query","value":"' + user +'"}]';
        this.pgrid.store.load({params: {filter: _filter, limit: 200 }});
    },save: function(){
       
    },
});

