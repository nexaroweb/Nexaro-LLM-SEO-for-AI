(function(){
	function debounce(fn, delay){
		let t;return function(){const ctx=this, args=arguments;clearTimeout(t);t=setTimeout(function(){fn.apply(ctx,args)},delay||200)}
	}
	function countWords(text){
		return (text.trim().match(/\S+/g)||[]).length;
	}
	function extractKeywords(text){
		text = text.toLowerCase();
		text = text.replace(/[.,/#!$%^&*;:{}=\-_`~()\[\]\"'<>؟،؛«»]/g, ' ');
		const words = text.split(/\s+/).filter(Boolean);
		const stop = (window.NexaroLLMS && Array.isArray(NexaroLLMS.stopwords)) ? NexaroLLMS.stopwords : [];
		const freq = {};
		for (let w of words){
			if (w.length < 3) continue;
			if (stop.includes(w)) continue;
			freq[w]=(freq[w]||0)+1;
		}
		return Object.keys(freq).sort(function(a,b){return freq[b]-freq[a]}).slice(0,15);
	}
	function updateValidation(){
		const ta = document.querySelector('#nexaro_llms_summary');
		if(!ta) return;
		const panel = document.querySelector('#nexaro-llms-validate');
		if(!panel) return;
		const txt = ta.value||'';
		const chars = txt.length;
		const words = countWords(txt);
		const keywords = extractKeywords(txt);
		const minChars = (window.NexaroLLMS && NexaroLLMS.minChars) ? parseInt(NexaroLLMS.minChars,10) : 180;
		const minKeywords = (window.NexaroLLMS && NexaroLLMS.minKeywords) ? parseInt(NexaroLLMS.minKeywords,10) : 5;
		let cls='good';
		let hints=[];
		if (chars < minChars){cls='warn';hints.push((NexaroLLMS&&NexaroLLMS.i18nMinChars)||'Consider adding more detail.');}
		if (keywords.length < minKeywords){cls='warn';hints.push((NexaroLLMS&&NexaroLLMS.i18nKeywords)||'Add more specific terms.');}
		panel.classList.remove('good','warn','bad');
		panel.classList.add(cls);
		panel.querySelector('.nx-metrics').textContent = (NexaroLLMS&&NexaroLLMS.i18nMetrics||'') + ' ' + chars + ' chars, ' + words + ' words, ' + keywords.length + ' keywords';
		panel.querySelector('.nx-hints').textContent = hints.join(' ');
	}
	document.addEventListener('input', debounce(updateValidation, 200), true);
	document.addEventListener('DOMContentLoaded', function(){
		updateValidation();
		const draftBtn = document.querySelector('#nx-generate-draft');
		if (draftBtn){
			draftBtn.addEventListener('click', function(){
				draftBtn.setAttribute('aria-busy','true');
				setTimeout(function(){draftBtn.removeAttribute('aria-busy')},1200);
			});
		}
	});
})();