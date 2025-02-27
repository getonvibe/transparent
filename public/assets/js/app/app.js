//Lets get this party started.
var leantime = leantime || {};

var themeColor = jQuery('meta[name=theme-color]').attr("content");
leantime.companyColor = themeColor;

var theme = jQuery('meta[name=color-scheme]').attr("content");
leantime.theme = theme;

var appURL = jQuery('meta[name=identifier-URL]').attr("content");
leantime.appUrl = appURL;

var leantimeVersion = jQuery('meta[name=leantime-version]').attr("content");
leantime.version = leantimeVersion;

//Backwards compatibility for some jQuery libs
jQuery(function() {
    jQuery.fn.size = function() {
        return this.length;
    };
});

jQuery(document).on('click', function (e) {
    jQuery('[data-toggle="popover"],[data-original-title]').each(function () {
        //the 'is' for buttons that trigger popups
        //the 'has' for icons within a button that triggers a popup
        if (!jQuery(this).is(e.target) && jQuery(this).has(e.target).length === 0 && jQuery('.popover').has(e.target).length === 0) {
            ((jQuery(this).popover('hide').data('bs.popover')||{}).inState||{}).click = false;  // fix for BS 3.3.6
        }
    });
});

//Run Prism code highlighting centrally



leantime.replaceSVGColors = function () {

    jQuery(document).ready(function(){

        if(leantime.companyColor != "#1b75bb") {
            jQuery("svg").children().each(function () {
                if (jQuery(this).attr("fill") == "#1b75bb") {
                    jQuery(this).attr("fill", leantime.companyColor);
                }
            });
        }

    });

};

leantime.replaceSVGColors();

jQuery(document).on('focusin', function(e) {
    if (jQuery(e.target).closest(".tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
        e.stopImmediatePropagation();
    }
});

//Set moment locale early in app creation
moment.locale(leantime.i18n.__("language.code"));


jQuery(document).ready(function(){
    jQuery(".confetti").click(function(){
        confetti.start();
    });

    tippy('[data-tippy-content]');

});


leantime.handleAsyncResponse = function(response)  {

    if(response !== undefined) {
        if(response.result !== undefined && response.result.html !== undefined){

            var content = jQuery(response.result.html);
            jQuery("body").append(content);
        }
    }
};






