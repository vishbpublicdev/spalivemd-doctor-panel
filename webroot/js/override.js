// Ext.override(Ext.Ajax, {
// });

// Ext.override(Ext.Ajax, {
//     defaultHeaders: {
//         'X-CSRF-Token': 'ABCD12345'
//     }
// });

Ext.override(Ext.form.action.Action, {
    submitEmptyText: false
});

Ext.override(Ext.data.Connection, {
    // config: {
    //     defaultHeaders: { 'X-CSRF-Token': 'ABCD12345'},
    // },
    // requestcomplete: function( conn, options, eOpts ) {
    //     console.log('beforerequest',conn, options, eOpts)
    //     // headers: {'X-CSRF-Token': '<?= json_encode($this->request->getParam('_csrfToken')) ?>'}
    // },
    // request: function(options) {
    //     this.callParent(arguments);

    //     console.log('request',options);
    //     options.operation._request._headers = { 'X-CSRF-Token': 'ABCD12345'};
    // },
    onRequestComplete: function(request) {
        // console.log('onRequestComplete',request);

        this.callParent(arguments);
        // if(request.result.status == 440){
        //     alert('Sesión Terminada');
        //     window.location = Panel.url('');
        // }
    }
});

Ext.override(Ext.data.proxy.Ajax, {
    // defaultActionMethods: {
    //     create: 'POST',
    //     read: 'POST',
    //     update: 'POST',
    //     destroy: 'POST'
    // },
    // actionMethods: {
    //     create: 'POST',
    //     read: 'POST',
    //     update: 'POST',
    //     destroy: 'POST'
    // },
    config: {
        binary: false,
        headers: undefined,
        paramsAsJson: false,
        withCredentials: false,
        useDefaultXhrHeader: true,
        username: null,
        password: null,
        actionMethods: {
            create: 'POST',
            read: 'POST',
            update: 'POST',
            destroy: 'POST'
        }
    },
});

Ext.override(Ext.util.Format, {
    thousandSeparator : ',',
    decimalSeparator : '.',
    usString : function(v) {
        return v.length > 0? v : '-';
    },
    usNumber : function(v) {
        Ext.util.Format.thousandSeparator = ','; Ext.util.Format.decimalSeparator = '.';
        return Ext.util.Format.number(v, '0,000.00');
    },
    usMoney : function(v) {
        Ext.util.Format.thousandSeparator = ','; Ext.util.Format.decimalSeparator = '.';
        return Ext.util.Format.currency(v, '$ ', 2);
    }
});

function string_to_slug(str) {
    str = str.replace(/^\s+|\s+$/g, ""); // trim
    str = str.toLowerCase();

    // remove accents, swap ñ for n, etc
    var from = "åàáãäâèéëêìíïîòóöôùúüûñç·/_,:;";
    var to = "aaaaaaeeeeiiiioooouuuunc------";

    for (var i = 0, l = from.length; i < l; i++) {
        str = str.replace(new RegExp(from.charAt(i), "g"), to.charAt(i));
    }

    str = str
        .replace(/[^a-z0-9 -]/g, "") // remove invalid chars
        .replace(/\s+/g, "-") // collapse whitespace and replace by -
        .replace(/-+/g, "-"); // collapse dashes

    return str;
}