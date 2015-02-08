// Custom jQuery for theme

// Script to toggle copyright popup
$( "button" ).click(function() {
    $( "#sugarcopy" ).toggle();

});

// Custom JavaScript for copyright pop-ups
$(function() {
    $( "#dialog, #dialog2" ).dialog({
        autoOpen: false,
        show: {
            effect: "blind",
            duration: 100
        },
        hide: {
            effect: "fade",
            duration: 1000
        }
    });
    $( "#powered_by" ).click(function() {
        $( "#dialog" ).dialog( "open" );
        $("#overlay").show().css({"opacity": "0.5"});
    });
    $( "#admin_options" ).click(function() {
        $( "#dialog2" ).dialog( "open" );
    });
});

// Back to top animation
$('#backtotop').click(function(event) {
    event.preventDefault();
    $('html, body').animate({scrollTop:0}, 500); // Scroll speed to the top
});

// Refresh function for refresh button on sidebar
function refresh(reload)
{
    window.location.reload(true);
}

// Tabs jQuery for Admin panel
$(function() {
    var tabs = $( "#tabs" ).tabs();
    tabs.find( ".ui-tabs-nav" ).sortable({
        axis: "x",
        stop: function() {
            tabs.tabs( "refresh" );
        }
    });
});

var checkContents = setInterval(function(){
    if ($(".list.view").length > 0 || $(".list.View").length > 0){ // Check if element has been found

       if($(".list.view").length > 0){
           element = $(".list.view");
       }

        if($(".list.View").length > 0){
            element = $(".list.View");
        }

        $('#dashletPanel th:not(:first-child)').attr("data-hide","phone, tablet");
        $('#subPanel th:not(:first-child)').attr("data-hide","phone, tablet");
        $(element).footable();
        $(".footable").find("th:first").attr("data-toggle","true");
        clearInterval(checkContents);
    }
},1);

// JavaScript fix to remove unrequired classes on smaller screens where sidebar is obsolete
$(window).resize(function () {
    if ($(window).width() < 979) {
        $('#bootstrap-container').removeClass('col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 sidebar main');
    }
    if ($(window).width() > 980 && $('.sidebar').is(':visible')) {
        $('#bootstrap-container').addClass('col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main');
    }
});

// Button to toggle list view search
$('.showsearch').click(function() {
    $('.search_form').toggle();
});

// jQuery to toggle sidebar
function loadSidebar() {
    $('#buttontoggle').click(function () {
        $('.sidebar').toggle();
        if ($('.sidebar').is(':visible')) {
            $.cookie('sidebartoggle', 'expanded');
            $('#bootstrap-container').addClass('col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2');
            $('#buttontoggle').css({
                'left': "+=215px"
            });
        }
        if ($('.sidebar').is(':hidden')) {
            $.cookie('sidebartoggle', 'collapsed');
            $('#bootstrap-container').removeClass('col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 col-sm-3 col-md-2 sidebar');
            $('#buttontoggle').css({
                'left': "-=215px"
            });
        }
    });
    var sidebartoggle = $.cookie('sidebartoggle');
    if (sidebartoggle == 'collapsed') {
        $('.sidebar').hide();
        $('#bootstrap-container').removeClass('col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 col-sm-3 col-md-2 sidebar');
        $('#buttontoggle').css({
            'left': "-=215px"
        });
    }
    else {
        $('#bootstrap-container').addClass('col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2');

    }
}
loadSidebar();
// End of custom jQuery