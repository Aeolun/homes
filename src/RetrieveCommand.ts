import {
  Generated,
  Kysely,
  MysqlDialect
} from 'kysely'
import {createPool} from 'mysql2'
import { DOMParser } from "@xmldom/xmldom";

interface InputInterface {
  region?: string
  province?: string
  type?: string
  refresh?: boolean
}

interface Database {
  page: {
    id: Generated<number>
    url: string
    content: string
    kind: string
  }
  property: {
    id: Generated<number>
    region: string
    province: string
    price: number
    coverage: number
    volume: number
    area: number
    building_area: number
    type: string
    train_line: string
    train_station: string
    station_distance: number
    address: string
    suumo_id: string
    suumo_js_id: string
    url: string
    postal_code: string
    state: string
    city: string
    property_type: string
    insert_date: string
  }
}

class RetrieveCommand {
  private types = {
    '030': 'Land'
    ,
    '021': 'Second-hand house'
    ,
    '020': 'New house'
    ,
    '011': 'Second-hand mansion'
    ,
    '010': 'New mansion'
  };

  private areas = {
    '010': {
      'name': 'Hokkaido',
      'provinces': {
        '01': 'Hokkaido',
      }
    },
    '020': {
      'name': 'Tohoku',
      'provinces': {
        '02': 'Aomori',
        '03': 'Iwate',
        '04': 'Miyagi',
        '05': 'Akita',
        '06': 'Yamagata',
        '07': 'Fukushima'
      }
    },
    '030': {
      'name': 'Kanto',
      'provinces': {
        '08': 'Ibaraki',
        '09': 'Tochigi',
        '10': 'Gunma',
        '11': 'Saitama',
        '12': 'Chiba',
        '13': 'Tokyo',
        '14': 'Kanagawa'
      }
    },
    '040': {
      'name': 'Hokuriku',
      'provinces': {
        '15': 'Niigata',
        '16': 'Toyama',
        '17': 'Ishikawa',
        '18': 'Fukui',
        '19': 'Yamanashi',
        '20': 'Nagano',
      }
    },
    '050': {
      'name': 'Tokai',
      'provinces': {
        '21': 'Gifu',
        '22': 'Shizuoka',
        '23': 'Aichi',
        '24': 'Mie',
      }
    },
    '060': {
      'name': 'Kansai',
      'provinces': {
        '25': 'Shiga',
        '26': 'Kyoto',
        '27': 'Osaka',
        '28': 'Hyogo',
        '29': 'Nara',
        '30': 'Wakayama'
      }
    },
    '080': {
      'name': 'Chugoku',
      'provinces': {
        '31': 'Tottori',
        '32': 'Shimane',
        '33': 'Okayama',
        '34': 'Hiroshima',
        '35': 'Yamaguchi'
      }
    },
    '070': {
      'name': 'Shikoku',
      'provinces': {
        '36': 'Tokushima',
        '37': 'Kagawa',
        '38': 'Ehime',
        '39': 'Kochi',
      }
    },
    '090': {
      'name': 'Kyushu',
      'provinces': {
        '40': 'Fukuoka',
        '41': 'Saga',
        '42': 'Nagasaki',
        '43': 'Kumamoto',
        '44': 'Oita',
        '45': 'Miyazaki',
        '46': 'Kagoshima',
        '47': 'Okinawa'
      }
    }
  };

  private base = 'https://suumo.jp/jj/bukken/ichiran/JJ012FC001/?ar={area}&bs={type}&ta={province}&pn={page}&ekTjCd=&ekTjNm=&kb=1&kj=9&km=1&kt=9999999&ta=13&tb=0&tj=0&tt=9999999&po=0&pj=1&pc=100';
  private perPage = 100;
  private database?: Kysely<Database>;
  private existingIds = [];

  private fields = {
    'id': './/input[@name="bsnc"]/@value',
  'jsId': './/input[@class="js-clipkey"]/@value',
  'value': './/dt[text()[contains(.,\'販売価格\')]]/../dd/span',
  'area': './/dt[text()[contains(.,\'土地面積\')]]/../dd',
  'address': './/dt[text()[contains(.,\'所在地\')]]/../dd',
  'station': './/dt[text()[contains(.,\'沿線・駅\')]]/../dd',
};

  private $optionalFields = {
    'building_area': './/dt[text()[contains(.,\'建物面積\')]]/../dd',
    'type': './/dt[text()[contains(.,\'間取り\')]]/../dd',
    'coverage': './/dt[text()[contains(.,\'建ぺい率・容積率\')]]/../dd'
  };

  public async execute(input: InputInterface) {
    if (!process.env.DATABASE_URL) {
      throw new Error("Need DATABASE_URL to be defined.")
    }

    const url = new URL(process.env.DATABASE_URL);
    this.database = new Kysely<Database>({
      // Use MysqlDialect for MySQL and SqliteDialect for SQLite.
      dialect: new MysqlDialect({
        pool: createPool({
          host: url.hostname,
          user: url.username,
          password: url.password,
          port: parseInt(url.port),
          database: url.pathname,
          charset: 'utf8'
        })
      })
    })

    if (input.refresh) {
      await this.database?.deleteFrom('page').execute()
    }

    const type = input.type ?? '030'

    const everything = await this.database?.selectFrom('property').select(['property.suumo_id']).execute()
    everything.forEach(item => {
      this.existingIds[item.suumo_id] = true
    })

    for(const areaId in this.areas) {
      const area = this.areas[areaId]

      for(const provinceId in this.areas[areaId].provinces) {
        const province = this.areas[areaId].provinces[provinceId]

        try {
          const page = this.getPage(1, areaId, provinceId, type)
          const totalItems = this.getTotalItems(page)

          this.parseItems(page, area.name, province, type)

          const pagesToLoad = Math.ceil(totalItems / this.perPage)
          console.log(`Loaded page 1/${pagesToLoad} for ${province} in ${area}`)

          if (totalItems) {
            for(let i = 2; i < pagesToLoad; i++) {
              const start = Date.now();

              const page = this.getPage(i, areaId, provinceId, type)

              this.parseItems(page, area.name, province, type)
              console.log(`Loaded page ${i}/${pagesToLoad} for ${province} in ${area}`)
            }
          }
        } catch(error) {
          throw new Error("Error when loading page: "+error)
        }

        console.log(`Done loading items for ${province}`)
      }
    }
  }

  private async parseItems(document: Document, area: string, province: string, type: string) {
    const items = document.xpath('//div[contains(@class, \'property_unit \')]')
    const newItems = []

    items.forEach(item => {
      try {
        const id = this.getElement(item, './/input[@name="bsnc"]/@value')
        const data = this.parseItem(item)
        data.insert_date = new Date().toISOString()
        data.province = province
        data.region = area
        data.property_type = type

        if (!this.existingIds[data.suumo_id]) {
          newItems.push(data)
        } else {
          const existingItem = await this.database?.selectFrom('property').select(['property.price']).where({
            suumo_id: data.suumo_id
          })
          if (existingItem.price !== data.price) {
            console.log(`Inserting new item with updated price. Original ${existingItem.price}, new ${data.price}.`)
            newItems.push(data)
          }
        }

      } catch(error) {
        console.log(`Error parsing item: ${error}`)
      }
    })

    await this.database?.insertInto('property').values(newItems)
  }

  private getElement(document: Document, xpath: string) {
    const el = document.xpath(xpath)
    if (el.length > 0) {
      return el[0].toString()
    }
    return undefined;
  }

  private parseItem(document: Document): Database['property'] {
    
    return {

    }
  }

  private async getPage(pageNr: number, areaId: string, provinceId: string, type: string) {
  }

  private async getTotalItems(document: Document) {

  }

  private async request(url: string, options: FetchRequestInit) {
    const response = await fetch(url, options)
    const parser = new DOMParser()
    return parser.parseFromString(await response.text())
  }
}
