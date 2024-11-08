import axios from "axios";

const singleParserForms = document.querySelectorAll('.singleParserForm');
const importLoader = document.getElementById('import-loader');
const formCanadaClass = 'singleParserForm_canada';

if( singleParserForms.length > 0 ){

    for (let i = 0; i < singleParserForms.length; i++) {
        const form = singleParserForms[i];

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            importLoader.textContent = '';
            const form = e.target.closest('.singleParserForm');
            const urlInput = form.querySelector('input[name="school-url"]')
            const url = urlInput.value;
    
            importLoader.textContent = 'Importing.';

            const requestUrl = form.classList.contains(formCanadaClass) ? 'canada-school' : 'school';
        
            axios.post(
                // 'https://us-central1-school-insiders.cloudfunctions.net/app/school',
                window.location.origin + '/wp-json/school-insiders/v1/' + requestUrl,
                { url: url }
            ).then(response => {
                console.log(response);
                importLoader.textContent = 'Importing finished. Navigate to the school posts to check the imported data.'
            }).catch(err => {
                console.error(err);
                importLoader.textContent = err.response.data.message;
            })
        })
        
    }

}