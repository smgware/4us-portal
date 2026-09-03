var smodules = [];

class SigmaCore {
    constructor(){
		this.name 						= "sigma_" + Math.floor(Math.random() * 1000);
		this.basepath 					= null;
		/*
		if(arguments.length == 1 ){
			for(var k in arguments[0]){
				this[k] = arguments[0][k];
			}
		}else{
			console.error("parameter error " + this.name );
		}
		*/
		smodules[this.name] 			= this;

		return this;
	}
	
	ucfirst(str) {
		return str.charAt(0).toUpperCase() + str.slice(1);
	}

	setPath(path){		
		this.basepath = path;
		return this;
		// console.log(this.basepath);
	}
	
	load(className){

		className = this.ucfirst(className);
	//	console.log(this.basepath + "/"+className + ".js");

		$.get(this.basepath + "/" + className + ".js", function(code) {

			let ClassRef = eval(`(function(){ ${code}; return ${className}; })()`);
			
			smodules[this.name] = new ClassRef();

		}).fail(function() {
			console.log("Error onLoad: " + className);
		});

	}
	
	/*
	send(){		
		ajaxquery[this.name]['ajax'] =    $.ajax({
			url:			this.url,
			type: 		this.type ?? 'POST',
			dataType:		this.dataType ?? "json",
			processData: 	this.processData ?? false,
			contentType: 	this.contentType ?? false,
			data:		this.formdata,
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
	*/

}


// window.Sigma = Sigma;
// window.sigma = Sigma;
