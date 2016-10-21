$(document).ready(function(){
        var smsSettings = {
                save : function() {
                        var data = {
                                savePath : $('#phone-number').val()
                        };
                        OC.msg.startSaving('#twofactor-sms-personal .msg');
                        $.post(OC.filePath('twofactor_sms', 'ajax', 'settings/personal.php'), data, smsSettings.afterSave);
                },
                afterSave : function(data){
                        OC.msg.finishedSaving('#twofactor-sms-personal .msg', data);
                }
        };
        $('#phone-number').blur(smsSettings.save);
        $('#phone-number').keypress(function( event ) {
                                                if (event.which == 13) {
                                                        event.preventDefault();
                                                        smsSettings.save();
                                                }
        });
});
