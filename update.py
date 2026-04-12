import requests
import json

URL = "https://goal-tv-highlight.hf.space/"

def fetch_data():
    try:
        res = requests.get(URL)
        res.raise_for_status()
        return res.json()
    except Exception as e:
        print("Error:", e)
        return []

def save_json(data):
    with open("fot-highlight.json", "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

def main():
    data = fetch_data()
    save_json(data)
    print("Updated fot-highlight.json")

if __name__ == "__main__":
    main()
