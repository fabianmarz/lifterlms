function Ajax (type, data, cache) {
	this.type = type;
	this.data = data;
	this.cache = cache;
	this.dataType = 'json';
	this.url = window.ajaxurl || window.llms.ajaxurl;
}

Ajax.prototype.get_sections = function () {
	jQuery.ajax({
		type 		: this.type,
		url			: this.url,
		data 		: this.data,
        cache		: this.cache,
        dataType	: this.dataType,
		success		: function(response) { section_template(response); }
	});
};

Ajax.prototype.get_lessons = function (section_id, section_position) {
	jQuery.ajax({
		type 		: this.type,
		url			: this.url,
		data 		: this.data,
        cache		: this.cache,
        dataType	: this.dataType,
		success		: function(response) { lesson_template(response, section_id, section_position); },
	});
};

Ajax.prototype.update_syllabus = function () {
	jQuery.ajax({
       // type 		: this.type,
		url			: this.url,
		data 		: this.data,
        cache		: this.cache,
        dataType	: this.dataType,
        success 	: function(response) { console.log(JSON.stringify(response, null, 4)); },
        error 		: function(errorThrown){ console.log(errorThrown); },
	});
};

Ajax.prototype.get_all_posts = function () {
	jQuery.ajax({
		type 		: this.type,
		url			: this.url,
		data 		: this.data,
        cache		: this.cache,
        dataType	: this.dataType,
		success		: function(response) { return_data(response); },
	});
};

Ajax.prototype.get_all_engagements = function () {
	jQuery.ajax({
		type 		: this.type,
		url			: this.url,
		data 		: this.data,
        cache		: this.cache,
        dataType	: this.dataType,
		success		: function(response) { return_engagement_data(response); },
	});
};