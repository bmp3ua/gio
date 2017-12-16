jQuery(document).ready( function($) {

    gio = {

		imgs : [], 
		busy : false,
		globalBusy : false,
		bad_requests : 0,
		folders : [],
		files : 0,
		progress : 0,
		success : 0,
		no_need : 0,
		rejected : 0,
		general_bytes : 0,
		mtt : null,
		gtt : null,
		
		init : function() {
			clearInterval( gio.mtt );
			clearInterval( gio.gtt );
			gio.imgs.length = 0; 
			gio.folders.length = 0;
			gio.busy = false;
			gio.globalBusy = false;
			gio.bad_requests = 0;
			gio.files = 0;
			gio.progress = 0;
			gio.success = 0;
			gio.no_need = 0;
			gio.rejected = 0;
			gio.general_bytes = 0;
			
			gio.setInfo();

            $.ajax({
                type : "POST",
                url : ajaxurl,
                data : { action : 'gio_cancel_optimize' },
                success : function() {
                },
                error : function() {
                }
            });

		},
		
		message : function( args = { 'result' : 'img_compressed', 'message' : '', 'img_info' : null } ) {
			if ( args.result == 'folder_started' ) {
				$('.gio-results').prepend('<div class="gio-result success">' + args.message + '</div>');
			}
			if ( args.result == 'img_compressed' ) {
				$('.gio-results').prepend('<div class="gio-result success">' + args.message + '</div>');
				gio.success++;
                gio.progress++;
			}
			else if ( args.result == 'img_passed' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
				gio.no_need++;
                gio.progress++;
			}
            else if ( args.result == 'google_rejected' ) {
                $('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
				gio.rejected++;
                gio.progress++;				
            }			
            else if ( args.result == 'google_soft_reject' ) {
                $('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
            }
			else if ( args.result == 'img_has_invalid_format' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
                gio.progress++;
			}			
			else if ( args.result == 'no_more_imgs' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
			}
			else if ( args.result == 'empty_dir' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
			}	
            else if ( args.result == 'google_hard_reject' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
            }
            else if ( args.result == 'server_reject' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
            }	
            else if ( args.result == 'empty_folder' ) {
				$('.gio-results').prepend('<div class="gio-result error">' + args.message + '</div>');
            }

			gio.setInfo( { 'img_info' : args.img_info } );
		},
		
		setCookie : function (name, value, options) {
		  options = options || {};

		  var expires = options.expires;

		  if (typeof expires == "number" && expires) {
			var d = new Date();
			d.setTime(d.getTime() + expires * 1000);
			expires = options.expires = d;
		  }
		  if (expires && expires.toUTCString) {
			options.expires = expires.toUTCString();
		  }

		  value = encodeURIComponent(value);

		  var updatedCookie = name + "=" + value;

		  for (var propName in options) {
			updatedCookie += "; " + propName;
			var propValue = options[propName];
			if (propValue !== true) {
			  updatedCookie += "=" + propValue;
			}
		  }

		  document.cookie = updatedCookie;
		},	

		getCookie : function (name) {
		  var matches = document.cookie.match(new RegExp(
			"(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
		  ));
		  return matches ? decodeURIComponent(matches[1]) : undefined;
		},

		deleteCookie : function(name) {
		  setCookie(name, "", {
			expires: -1
		  })
		},

		implode : function ( glue, pieces ) {	// Join array elements with a string
			return ( ( pieces instanceof Array ) ? pieces.join ( glue ) : pieces );
		},

		explode : function ( delimiter, string ) {	// Split a string by string

			var emptyArray = { 0: '' };

			if ( arguments.length != 2
				|| typeof arguments[0] == 'undefined'
				|| typeof arguments[1] == 'undefined' )
			{
				return null;
			}

			if ( delimiter === ''
				|| delimiter === false
				|| delimiter === null )
			{
				return false;
			}

			if ( typeof delimiter == 'function'
				|| typeof delimiter == 'object'
				|| typeof string == 'function'
				|| typeof string == 'object' )
			{
				return emptyArray;
			}

			if ( delimiter === true ) {
				delimiter = '1';
			}

			return string.toString().split ( delimiter.toString() );
		},	
		
		optimizeImages : function () {
			var currentImg, data;
			if ( gio.imgs.length > 0 ) {
				if ( !gio.busy ) {
					gio.busy = true;
					currentImg = gio.imgs[0];
					gio.optimizeSingleImg( currentImg )( currentImg );
				}
			}
			else {
				gio.busy = false;
				clearInterval( gio.mtt );
				if ( gio.folders.length > 0 ) {
                    gio.folders.shift();
                    gio.globalBusy = false;
					if ( gio.folders.length == 0 ) {
						clearInterval(gio.gtt);
						$.ajax({
							type : "POST",
							url : ajaxurl,
							data : { action : 'gio_cancel_optimize' },
							success : function() {
							},
							error : function() {
							}
						});						
					}
                }
				gio.message( { 'result' : 'no_more_imgs', 'message' : 'there are no more files in selected folder' } );
			}
			
			
		},
		
		optimizeSingleImg : function ( img ) {
			global_img = img;
			return function( img ) {

				$('.info .current-file').text( img );

				if ( !global_img.match( /.jpg|.png/ ) ) {
					gio.message( { 'result' : 'img_has_invalid_format', 'message' : 'file ' + global_img + ' has invalid format' } );
					gio.imgs.shift();
					gio.busy = false;
					return;				
				}
				if ( gio.bad_requests > 1440 ) {
					gio.imgs.length = 0;
					gio.bad_requests = 0;
					busy = false;
					clearInterval( mtt );
					gio.message( { 'result' : 'google_hard_reject', 'message' : 'google api is busy now, try operation later' } );
					return;
				}			
				$.ajax({
					type : "POST",
					url : ajaxurl,
					data : { action : 'gio_optimize_single_img', img : global_img },
					success: function(data)	{
						data = JSON.parse(data);
						if ( data.result == 1 ) {
							gio.message( { 'result' : 'img_compressed', 'message' : data.content, 'img_info' : data.current_img } );
							gio.bad_requests = 0;
							gio.imgs.shift();
							gio.busy = false;
						}
                        else if ( data.result == 2 ) {
                            gio.message( { 'result' : 'google_rejected', 'message' : data.content, 'img_info' : { type : 'google_rejected', file : gio.getFileName( data.current_img.file ) } } );
                            gio.bad_requests = 0;
                            gio.imgs.shift();
                            gio.busy = false;
                        }
                        else if ( data.result == 3 ) {
                            gio.message( { 'result' : 'img_passed', 'message' : data.content } );
                            gio.bad_requests = 0;
                            gio.imgs.shift();
                            gio.busy = false;
                        }						
						else if ( data.result == 0 ) {
							if ( data.content == 'http request error, try later' ) {
								gio.message( { 'result' : 'google_soft_reject', 'message' : 'google api returned busy state, next request will be send in 5 seconds' } );
								setTimeout( gio.optimizeSingleImg( global_img ), 5000);
								gio.bad_requests++;
							}
						}
					},
					error: function() {
							gio.message( { 'result' : 'server_reject', 'message' : 'server does not answer, next request will be send in 5 seconds' } );
							setTimeout( gio.optimizeSingleImg( global_img ), 5000);
							gio.bad_requests++;					
					}				
				});
			}		
		},

        optimizeFolder : function(folder) {
			$.ajax({
				type: "POST",
				url: ajaxurl,
				data: {action: 'gio_start_folder_optimize', dir: folder},
				success: function (data) {
					var result;
					result = JSON.parse(data);
					if (result.result == 1) {
						gio.imgs = result.data;
						gio.message( { 'result' : 'folder_started', 'message' : result.content } );
						gio.mtt = setInterval(gio.optimizeImages, 200);
					}
					else {
						gio.message( { 'result' : 'empty_folder', 'message' : result.content } );
					}
				}
			});
        },

		setInfo : function( args ) {
            $('.info .total').text( gio.files );
		    $('.info .progress').text( gio.progress );
            $('.info .digit.success').text( gio.success );
            $('.info .no-need').text( gio.no_need );
            $('.info .rejected').text( gio.rejected );
			$('.info .rejected-imgs').text( '' );
            if ( args && args.img_info && args.img_info.type == 'success' ) {
				var percentage;
                $('.info .saving .saved-bytes').html( parseInt( $('.info .saving .saved-bytes').text() ) + parseInt( args.img_info.save ) );
				$('.info .saving .general-bytes').html( parseInt( $('.info .saving .general-bytes').text() ) + parseInt( args.img_info.start ) );
				percentage = parseInt( $('.info .saving .saved-bytes').text() ) / parseInt( $('.info .saving .general-bytes').text() );
				$('.info .general-percentage').html( percentage.toPrecision(4)*100 + '%' );
			}
            if ( args && args.img_info && args.img_info.type && args.img_info.type == 'google_rejected' ) {
                $('.info .rejected-imgs').append( '<div>' + gio.getFileName( args.img_info.file ) + '</div>' );
            }
        },
		
		getFileName : function( file ) {
			var r = file.match( /wp-content.+/ );
			if ( r[0] ) return r[0];
			else return '';
		}
	
	}



    $('.admin-gio-box button.gio-start-optimize').on( 'click', function(e) {
		gio.init();
        $.ajax({
            type : "POST",
            url : ajaxurl,
            data : { action : 'gio_start_optimize', dir : $('div.gio-dest').text() },
            success: function(data)
            {
                var result;
                result = JSON.parse(data);
                if ( result.result == 1) {
                    gio.folders = result.data;
                    if ( gio.folders.length > 0 ) {
						gio.progress = 0;
						gio.files = result.task_files;
                        gio.gtt = setInterval(function () {
                            if ( !gio.globalBusy && gio.folders[0] && gio.folders[0] != '' ) {
                                gio.globalBusy = true;
                                gio.currentFolder = gio.folders[0];
                                gio.optimizeFolder(gio.currentFolder);
                            }
                        }, 200);
                    }
                }
                else {
					gio.message( { 'result' : 'empty_folder', 'message' : result.content } );
					gio.init();
                }
            }
        });
    });
	
	$('.admin-gio-box button.gio-cancel-optimize').on( 'click', function(e) {
        gio.init();		
	});

	$('.target-dir').on( 'click', function(e) {
		if ( !gio.busy ) {
            var _target = $(e.target).parent();
            $('.destination-dirs').find('.target-dir').removeClass('selected');
            if ( $(_target).hasClass('parent-dir') ) {
                if (!$(_target).hasClass('selected')) {
                    $(_target).addClass('selected');
                    $(_target).parents('.target-box').find('.child-dir').addClass('selected');
                }
                else {
                    $(_target).removeClass('selected');
                    $(_target).parents('.target-box').find('.child-dir').removeClass('selected');
                }
            }
            else {
                $(_target).parents('.target-box').find('.target-dir').removeClass('selected');
            	if ( !$(_target).hasClass('selected') ) {
                    $(_target).addClass('selected');
				}
				else {
                    $(_target).removeClass('selected');
				}
			}
            $('#gio-dest').text($(_target).attr('data-value'));
        }
	});


});
