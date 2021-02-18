jQuery(document).ready(function($) {
var toggleNavs = jQuery('.toggle-content');
  toggleNavs.each(function(){
      var toggleNav = jQuery(this),
        toggleNavTrigger = toggleNav.find('.toggle-nav');
      
      toggleNavTrigger.on('click', function(event){
        event.preventDefault();
        toggleNav.toggleClass('active');
      });
    });
  jQuery(document).on('click', function(event){
      ( !$(event.target).is('.toggle-nav') && !$(event.target).is('.toggle-nav span') ) && toggleNavs.removeClass('active');
    });
  if (jQuery('.breadcrumb.community-breadcrumb ol li .field--name-field-conference').length == '0'){ jQuery('.breadcrumb__item.community-breadcrumb-item:nth-child(2) .breadcrumb__item-separator').css("display","none") }

});
