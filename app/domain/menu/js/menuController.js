leantime.menuController = (function () {

    //Variables

    //Constructor
    (function () {
        jQuery(document).ready(
            function () {
                _initProjectSelector();
                _initLeftMenuHamburgerButton();
                _initProjectSelectorToggle();
            }
        );

    })();

    //Functions

    var toggleSubmenu = function (submenuName) {

        if(submenuName === "") {
            return;
        }

        var submenuDisplay = jQuery('#submenu-' + submenuName).css('display');
        var submenuState = '';

        if (submenuDisplay == 'none') {
            jQuery('#submenu-' + submenuName).css('display', 'block');
            jQuery('#submenu-icon-' + submenuName).removeClass('fa-angle-right');
            jQuery('#submenu-icon-' + submenuName).addClass('fa-angle-down');
            submenuState = 'open';
        } else {
            jQuery('#submenu-' + submenuName).css('display', 'none');
            jQuery('#submenu-icon-' + submenuName).removeClass('fa-angle-down');
            jQuery('#submenu-icon-' + submenuName).addClass('fa-angle-right');
            submenuState = 'closed';
        }

        jQuery.ajax({
            type : 'PATCH',
            url  : leantime.appUrl + '/api/submenu',
            data : {
                submenu : submenuName,
                state   : submenuState
            }
        });
    }

    var _initProjectSelector = function () {

        jQuery(".project-select").chosen();

    };

    var _initLeftMenuHamburgerButton = function () {


        var newWidth = 68;
        if(window.innerWidth < 576) {
            jQuery(".mainwrapper").removeClass("menuopen");
            jQuery(".mainwrapper").addClass("menuclosed");
        }


        /*

        if (jQuery('.barmenu').hasClass('open')) {

            jQuery('.rightpanel').css({marginLeft: '240px'});
            jQuery('.header').animate({marginLeft: '240px'}, 'fast');
            newWidth =  jQuery('.header').parent().width() - 240;
            jQuery('.header').animate({width:newWidth}, 'fast');
            jQuery('.logo, .leftpanel').css({marginLeft: 0});

            jQuery('.logo').show();
            jQuery('.logo, #expandedMenu').css({display: 'block'});
            jQuery("#minimizedMenu").css({display: 'none'});

        } else {

            jQuery('.rightpanel').css({marginLeft: '68px'});
            jQuery('.header').animate({marginLeft: '68px'}, 'fast');

            newWidth =  jQuery('.header').parent().width() - 68;
            jQuery('.header').animate({width:newWidth}, 'fast');
            jQuery('.logo, .leftpanel').css({marginLeft: '0'});
            jQuery('.logo').hide();
            jQuery('.logo, #expandedMenu').css({display: 'none'});
            jQuery("#minimizedMenu").css({display: 'block'});

        }*/

        jQuery('.barmenu').click(function () {

            if (jQuery(".mainwrapper").hasClass('menuopen')) {

                jQuery(".mainwrapper").removeClass("menuopen");
                jQuery(".mainwrapper").addClass("menuclosed");

                //If it doesn't have the class open, the user wants it to be open.
                leantime.menuRepository.updateUserMenuSettings("closed");

            } else {

                jQuery(".mainwrapper").removeClass("menuclosed");
                jQuery(".mainwrapper").addClass("menuopen");

                //If it doesn't have the class open, the user wants it to be open.
                leantime.menuRepository.updateUserMenuSettings("open");

            }

        });

    };

    var _initProjectSelectorToggle = function (id, element) {

        jQuery(document).on('click', '.project-selector .dropdown-menu', function (e) {
            e.stopPropagation();
        });

    };

    var toggleClientListHorizontal = function (id, element) {

        jQuery(".selectorList.projectList li").not(".nav-header, .fixedBottom").hide();

        jQuery(".client_" + id).show();
        jQuery(".client_" + id).show();

        jQuery(".selectorList.clientList li").removeClass("active");
        jQuery(element).addClass("active");


    };

    var toggleHierarchy = function (selectedParent, target, origin) {

        jQuery(".projectList.selectorList li").not(".nav-header, .fixedBottom").removeClass("groupShow");
        jQuery(".projectList.selectorList li").not(".nav-header, .fixedBottom").addClass("hideGroup");

        jQuery("."+target+"List.selectorList li").not(".nav-header, .fixedBottom").removeClass("groupShow");
        jQuery("."+target+"List.selectorList li").not(".nav-header, .fixedBottom").addClass("hideGroup");

        jQuery("."+target+"List.selectorList li."+target+"Group-"+selectedParent).removeClass("hideGroup");
        jQuery("."+target+"List.selectorList li."+target+"Group-"+selectedParent).addClass("groupShow");

        updateActiveParent(origin, selectedParent);

        jQuery("."+target+"List.selectorList li").removeClass("activeChild");
        jQuery("."+origin+"List.selectorList li").removeClass("activeChild");

        jQuery(".parent-"+selectedParent).addClass("activeChild");

        jQuery(".clientController").removeClass("groupShow");
        jQuery(".clientController").addClass("hideGroup");

        jQuery("."+target+"List.selectorList li.groupShow").each(function() {
           var clientId = jQuery(this).attr("data-client");
            jQuery(".clientController.clientIdHead-"+clientId+".clientGroupParent-"+selectedParent).removeClass("hideGroup");
            jQuery(".clientController.clientIdHead-"+clientId+".clientGroupParent-"+selectedParent).addClass("groupShow");
        });

    };

    var toggleClientList = function (id, element, set="") {

        //MEthod is executed on click and does the oposite of the current state.
        //(eg when closed->open; when open->close)
        //To force a state we need to ensure it is the oposite of the state requested



        if(set === "closed") {
            jQuery(element).removeClass("closed");
            jQuery(element).removeClass("open");
            jQuery(element).addClass("open");
        }else if(set === "open"){
            jQuery(element).removeClass("open");
            jQuery(element).removeClass("closed");
            jQuery(element).addClass("closed");
        }

        var activeProgramParent = jQuery(".programList .activeChild").attr("data-program-id");
        var activeStrategyParent = jQuery(".strategyList .activeChild").attr("data-strategy-id");
        var activeParent = 'noparent';

        if(activeStrategyParent !== undefined && activeStrategyParent !== ''){
            activeParent = activeStrategyParent;
        }

        if(activeProgramParent !== undefined && activeProgramParent !== ''){
            activeParent = activeProgramParent;
        }

        if(jQuery(element).hasClass("open")){

            jQuery(".clientId-"+activeParent+"-" + id).hide("fast");
            jQuery(element).removeClass("open");
            jQuery(element).addClass("closed");

            jQuery(element).find("i").removeClass("fa-angle-down");
            jQuery(element).find("i").addClass("fa-angle-right");

            updateClientDropdownSetting(activeParent+"-"+id, "closed");

        }else{

            jQuery(".clientId-"+activeParent+"-" + id).show("fast");
            jQuery(element).removeClass("closed");
            jQuery(element).addClass("open");

            jQuery(element).find("i").removeClass("fa-angle-right");
            jQuery(element).find("i").addClass("fa-angle-down");

            updateClientDropdownSetting(activeParent+"-"+id, "open");

        }

    };

    let updateClientDropdownSetting = function(clientId, state) {

        jQuery.ajax({
            type : 'PATCH',
            url  : leantime.appUrl + '/api/submenu',
            data : {
                submenu : "clientDropdown-"+clientId,
                state   : state
            }
        });

    };

    let updateActiveParent = function(parent, state) {

        jQuery.ajax({
            type : 'PATCH',
            url  : leantime.appUrl + '/api/submenu',
            data : {
                submenu : parent,
                state   : state
            }
        });

    };

    // Make public what you want to have public, everything else is private
    return {
        toggleClientList:toggleClientList,
        toggleSubmenu:toggleSubmenu,
        toggleHierarchy:toggleHierarchy
    };

})();
