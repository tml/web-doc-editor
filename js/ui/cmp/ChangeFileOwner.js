Ext.namespace('ui','ui.cmp','ui.cmp._ChangeFileOwner');

ui.cmp._ChangeFileOwner.store = new Ext.data.Store({
    proxy : new Ext.data.HttpProxy({
        url : './do/getVCSUsers'
    }),
    reader : new Ext.data.JsonReader({
        root          : 'Items',
        totalProperty : 'nbItems',
        fields        : [
            {name : 'id'},
            {name : 'userName'}
        ]
    }),
    sortInfo: {
        field: 'userName',
        direction: 'ASC'
    }
});


ui.cmp.ChangeFileOwner = Ext.extend(Ext.Window,
{
    title      : _('Change file\'s owner'),
    iconCls    : 'iconSwitchLang',
    width      : 550,
    height     : 255,
    layout     : 'form',
    resizable  : false,
    modal      : true,
    autoScroll : true,
    closeAction: 'close',
    padding    : 10,
    buttons    : [{
        text    : _('Save'),
        disabled: true,
        handler : function()
        {
            var win = this.ownerCt.ownerCt,
                newOwner = win.items.items[1].items.items[0].getValue();
            
            new ui.task.ChangeFileOwner({
                fileIdDB : win.fileIdDB,
                newOwner : newOwner,
                from     : win
            });
            
        }
    },{
        text    : _('Close'),
        handler : function()
        {
            var win = this.ownerCt.ownerCt;
            win.close();
        }
    }],

    initComponent : function()
    {
        var win = this;
        
        Ext.apply(this,
        {
            defaults: {
                labelWidth : 120
            },
            items : [{
                xtype   : 'fieldset',
                title   : _('Information'),
                iconCls : 'iconInfo',
                width   : 515,
                items   : [{
                    xtype:'displayfield',
                    fieldLabel: _('File'),
                    value: this.fileFolder + this.fileName
                },{
                    xtype:'displayfield',
                    fieldLabel: _('Current owner'),
                    value: this.currentOwner
                }]
            },{
                xtype   : 'fieldset',
                title   : _('Action'),
                iconCls : 'iconSwitchLang',
                width   : 515,
                items   : [{
                    xtype         : 'combo',
                    name          : 'newOwner',
                    fieldLabel    : _('New owner'),
                    editable      : false,
                    store         : ui.cmp._ChangeFileOwner.store,
                    triggerAction : 'all',
                    valueField    : 'userName',
                    displayField  : 'userName',
                    listeners     : {
                        afterrender : function()
                        {
                            this.store.load();
                        },
                        select : function()
                        {
                            win.fbar.items.items[0].enable();
                        }
                    }
                }]
            }]
        });
        
        ui.cmp.ChangeFileOwner.superclass.initComponent.call(this);
        
        this.show();
    }
});