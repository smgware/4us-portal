var ajaxquery = [];

class AJAX {
    constructor(){

		this.name 						= "ajax_" + Math.floor(Math.random() * 1000)
		this.timeout 						= 800000;
		this.url 							= "index-router";
		this.formData 						= new FormData();
		this.target 						= $(".ajax-content[data-name='index-router']");

		this.function_prepare				= function(result){};
		this.function_success				= function(result){};
		this.function_complete				= function(result){};
		this.function_xhr					= function(input){var xhr = new window.XMLHttpRequest(); return xhr; };
		this.function_error				    = function(xhr, status, error){
              let message = "AJAX error: " + this.name +" " + status + " " + this.url;

			  if (xhr.status === 404) {
                    message = "API error";
                } else if (xhr.status === 500) {
                    message = "Internal Server Error";
                } else if (xhr.responseJSON?.message) {
                    message = xhr.responseJSON.message;
                }
		       console.error(message);
		};

		if(arguments.length == 1 ){
			for(var k in arguments[0]){
				this[k] = arguments[0][k];
			}
		}else{
			console.error("ajax parameter error " + this.name );
		}

		if (this.formdata instanceof FormData) {
			this.formData = this.formdata;
		}
		this.formdata = this.formData;

		ajaxquery[this.name] 			= this;
		ajaxquery[this.name]['ajax'] 	= null;

		return this;
	}

	send(){
		let requestType = this.type ?? 'POST';
		let requestUrl = this.url;
		let requestData = this.formData;
		let processData = this.processData ?? false;
		let contentType = this.contentType ?? false;

		if (requestType.toUpperCase() === "GET" && this.formData instanceof FormData) {
			const queryParams = new URLSearchParams();

			for (const [key, value] of this.formData.entries()) {
				queryParams.append(key, value);
			}

			const queryString = queryParams.toString();
			if (queryString !== "") {
				requestUrl += (requestUrl.indexOf("?") === -1 ? "?" : "&") + queryString;
			}

			requestData = null;
			processData = true;
			contentType = undefined;
		}

		ajaxquery[this.name]['ajax'] =    $.ajax({
			url:			requestUrl,
			type: 		    requestType,
			dataType:		this.dataType ?? "json",
			processData: 	processData,
			contentType: 	contentType,
			data:		    requestData,
			timeout:		this.timeout ?? 10000,
			functionname:	this.name,
			xhr:			this.function_xhr,
			beforeSend: function (response) {
				if( ajaxquery?.[(this.functionname ?? null)] ){
					ajaxquery?.[(this.functionname ?? null)].function_prepare(response);
				}
			},
			success: function (response) {
				if( ajaxquery?.[(this.functionname ?? null)] ){
					ajaxquery?.[(this.functionname ?? null)].function_success(response);
				}
			},
			error: function (xhr, status, error) {
				if( ajaxquery?.[(this.functionname ?? null)] ){
					ajaxquery?.[(this.functionname ?? null)].function_error(xhr, status, error);
				}
			},
			complete: function (response) {
				if( ajaxquery?.[(this.functionname ?? null)] ){
					ajaxquery?.[(this.functionname ?? null)].function_complete(response);
				}
			}
        });

	}

}



window.AJAX = AJAX;
window.Ajax = AJAX;
