
if(!alertify.add){
    alertify.dialog('add',function factory(){
        return{
            main:function(title,message){
                this.setHeader(title);
                this.setContent(message);
            },
        }
    });
}

if(!alertify.sheet){
    alertify.dialog('sheet',function factory(){
        return{
            main:function(title,message){
                this.setHeader(title);
                this.setContent(message);
            },
        }
    });
}

if(!alertify.popup){
    alertify.dialog('popup',function factory(){
        return{
            main:function(title,message){
                this.setHeader(title);
                this.setContent(message);
            },
        }
    });
}

if(!alertify.filter){
    alertify.dialog('filter',function factory(){
        return{
            main:function(title,message){
                this.setHeader(title);
                this.setContent(message);
            },
        }
    });
}

function randomString(length) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';

    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }

    return result;
}

$(document).on("click", "[data-name='ajax-content'] a, .ajax-content a", function (event) {
    event.preventDefault();
    event.stopPropagation();

    const $link = $(this);
    const $ajaxContent = $link.closest("[data-name='ajax-content'], .ajax-content");
    const ajaxKey = $ajaxContent.data("key");

    if (!ajaxKey || !ajaxquery?.[ajaxKey]) {
        return false;
    }

    const href = $link.attr("href") || "";
    const url = new URL(href, window.location.href);
    const page = url.searchParams.get("page");

    if (page !== null) {
        const ajaxCall = ajaxquery[ajaxKey];
        const ajaxUrl = new URL(ajaxCall.url, window.location.href);

        ajaxUrl.searchParams.set("page", page);
        ajaxCall.url = ajaxUrl.pathname + ajaxUrl.search + ajaxUrl.hash;

        if (ajaxCall.formData instanceof FormData) {
            ajaxCall.formData.delete("page");
        }

        ajaxCall.send();
    }

    return false;
});
